<?php

namespace App\Http\Controllers;

use App\Actions\Tenants\DeleteTenant;
use App\Models\CentralAdmin;
use App\Models\Tenant;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class CentralTenantLifecycleController extends Controller
{
    private const DELETION_STALE_MINUTES = 5;

    public function suspend(Request $request, Tenant $tenant, CentralAuditLogger $audit): RedirectResponse
    {
        $request->validate(['confirmed' => ['required', 'accepted']], ['confirmed.accepted' => 'Tenant suspension must be explicitly confirmed.']);
        abort_unless($tenant->status === 'active', 409);
        $tenant->update(['status' => 'suspended', 'suspended_at' => now()]);
        $audit->log('tenant.suspended', 'Tenant suspended after administrator confirmation.', tenantId: (string) $tenant->getTenantKey(), request: $request);

        return back()->with('status', 'Tenant suspended.');
    }

    public function activate(Request $request, Tenant $tenant, CentralAuditLogger $audit): RedirectResponse
    {
        abort_unless($tenant->provisioning_status === 'active', 409);

        $unresolvedDeletion = TenantDeletionRecord::query()
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->whereIn('status', ['started', 'failed'])
            ->exists();

        abort_if($unresolvedDeletion, 409, 'This tenant has an unresolved deletion attempt. Retry or resolve deletion cleanup before reactivating it.');

        $tenant->update(['status' => 'active', 'suspended_at' => null]);
        $audit->log('tenant.activated', 'Tenant activated.', tenantId: (string) $tenant->getTenantKey(), request: $request);

        return back()->with('status', 'Tenant activated.');
    }

    public function deletionStatus(string $tenantId): JsonResponse
    {
        $history = TenantDeletionRecord::query()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        if (! $history instanceof TenantDeletionRecord) {
            return response()->json([
                'tenant_id' => $tenantId,
                'status' => 'starting',
                'progress' => 5,
                'message' => 'Preparing permanent deletion…',
                'completed' => false,
                'failed' => false,
            ]);
        }

        $status = (string) $history->status;
        $results = (array) $history->cleanup_results;
        $completed = in_array($status, ['completed', 'completed_with_warnings'], true);
        $failed = $status === 'failed';

        if ($completed) {
            return response()->json([
                'tenant_id' => $tenantId,
                'status' => $status,
                'progress' => 100,
                'message' => $status === 'completed_with_warnings'
                    ? 'Tenant deleted. Some infrastructure cleanup needs manual follow-up.'
                    : 'Tenant and managed infrastructure were deleted.',
                'completed' => true,
                'failed' => false,
                'cleanup_warnings' => $history->hasCleanupFailures(),
                'cleanup_results' => $results,
                'redirect' => route('central.tenants.index'),
            ]);
        }

        if ($failed) {
            return response()->json([
                'tenant_id' => $tenantId,
                'status' => $status,
                'progress' => 100,
                'message' => 'Deletion stopped before the central tenant record could be removed.',
                'completed' => false,
                'failed' => true,
                'cleanup_results' => $results,
            ]);
        }

        if ($this->deletionIsStale($history)) {
            return response()->json([
                'tenant_id' => $tenantId,
                'status' => 'stale',
                'progress' => 100,
                'message' => 'Deletion has not reported progress recently and may have stopped. Review the cleanup record, then retry permanent deletion if appropriate.',
                'completed' => false,
                'failed' => true,
                'cleanup_results' => $results,
            ]);
        }

        [$progress, $message] = $this->deletionProgress($history, $results);

        return response()->json([
            'tenant_id' => $tenantId,
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'completed' => false,
            'failed' => false,
            'cleanup_results' => $results,
        ]);
    }

    public function destroy(Request $request, Tenant $tenant, DeleteTenant $deleteTenant, CentralAuditLogger $audit): JsonResponse|RedirectResponse
    {
        abort_unless($tenant->status === 'suspended', 409, 'Suspend the tenant before permanently deleting it.');
        $tenantId = (string) $tenant->getTenantKey();

        $activeDeletion = TenantDeletionRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'started')
            ->latest('id')
            ->first();

        if ($activeDeletion instanceof TenantDeletionRecord && ! $this->deletionIsStale($activeDeletion)) {
            $message = 'Permanent deletion is already in progress for this tenant.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'errors' => ['deletion' => [$message]]], 409)
                : back()->withErrors(['deletion' => $message]);
        }

        $validated = $request->validate([
            'tenant_id_confirmation' => ['required', 'string'],
            'current_password' => ['required', 'string'],
        ]);

        if (! hash_equals($tenantId, trim((string) $validated['tenant_id_confirmation']))) {
            throw ValidationException::withMessages(['tenant_id_confirmation' => 'The tenant ID confirmation does not match.']);
        }

        $admin = Auth::guard('central')->user();
        if (! $admin instanceof CentralAdmin || ! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages(['current_password' => 'The Control Center password is incorrect.']);
        }

        try {
            $history = $deleteTenant->handle($tenant, $admin);
        } catch (Throwable $e) {
            report($e);
            $audit->log('tenant.deletion_failed', 'Permanent tenant deletion failed before the central tenant record could be removed.', tenantId: $tenantId, context: ['error' => $e->getMessage()], request: $request);

            return $request->expectsJson()
                ? response()->json(['message' => 'Tenant deletion failed.', 'errors' => ['deletion' => [$e->getMessage()]]], 422)
                : back()->withErrors(['deletion' => $e->getMessage()]);
        }

        $hasWarnings = $history->hasCleanupFailures();
        $message = $hasWarnings
            ? 'Tenant permanently deleted. Some infrastructure cleanup needs manual attention; the cleanup history was retained in Central Activity.'
            : 'Tenant permanently deleted and managed infrastructure cleanup completed.';

        if ($request->expectsJson()) {
            return response()->json([
                'deleted' => true,
                'tenant_id' => $tenantId,
                'cleanup_warnings' => $hasWarnings,
                'cleanup_results' => $history->cleanup_results,
                'message' => $message,
                'redirect' => route('central.tenants.index'),
            ]);
        }

        return redirect()->route('central.tenants.index')->with('status', $message);
    }

    private function deletionIsStale(TenantDeletionRecord $history): bool
    {
        if ((string) $history->status !== 'started' || $history->updated_at === null) {
            return false;
        }

        return $history->updated_at->lt(now()->subMinutes(self::DELETION_STALE_MINUTES));
    }

    /** @param array<string, mixed> $results @return array{int, string} */
    private function deletionProgress(TenantDeletionRecord $history, array $results): array
    {
        if (! array_key_exists('platform_domain', $results)) {
            return [15, 'Removing the permanent platform domain…'];
        }

        $customDomains = $history->custom_domains ?? [];
        $completedCustoms = count(array_filter(
            array_keys($results),
            static fn (string $key): bool => str_starts_with($key, 'custom_domain:'),
        ));

        if ($completedCustoms < count($customDomains)) {
            $ratio = $completedCustoms / max(1, count($customDomains));
            $progress = 25 + (int) floor($ratio * 25);
            $domain = $customDomains[$completedCustoms];

            return [$progress, "Removing custom domain {$domain}…"];
        }

        if (! array_key_exists('storage', $results)) {
            return [55, 'Removing tenant storage…'];
        }

        if (! array_key_exists('database', $results)) {
            return [72, 'Removing the tenant database…'];
        }

        if (! array_key_exists('central_record', $results)) {
            return [90, 'Finalizing central tenant records…'];
        }

        return [96, 'Finalizing permanent deletion…'];
    }
}
