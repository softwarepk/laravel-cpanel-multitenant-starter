<?php

namespace App\Http\Controllers;

use App\Actions\Tenants\DeleteTenant;
use App\Jobs\DeleteTenantJob;
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

class QueuedTenantDeletionController extends Controller
{
    private const int STALE_DELETION_MINUTES = 10;

    public function destroy(Request $request, Tenant $tenant, DeleteTenant $deleteTenant, CentralAuditLogger $audit): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($tenant->status, ['suspended', 'failed'], true), 409, 'Suspend an active tenant before deleting it. Tenants with failed provisioning may be deleted directly.');
        $tenantId = (string) $tenant->getTenantKey();
        $restoreStatus = (string) $tenant->status;

        $latestDeletion = TenantDeletionRecord::query()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        if ($latestDeletion instanceof TenantDeletionRecord
            && (string) $latestDeletion->status === 'started'
            && ! $this->deletionIsStale($latestDeletion)) {
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

        $history = $deleteTenant->begin($tenant, $admin);
        $tenant->update(['status' => 'deleting']);

        try {
            DeleteTenantJob::dispatch($tenantId, (int) $history->getKey(), (int) $admin->getKey(), $restoreStatus);
        } catch (Throwable $e) {
            report($e);
            $history->update(['status' => 'failed', 'completed_at' => now()]);
            $tenant->update(['status' => $restoreStatus]);
            $audit->log('tenant.deletion_failed', 'Tenant deletion could not be queued.', tenantId: $tenantId, context: ['deletion_record_id' => $history->getKey(), 'error' => $e->getMessage()], request: $request);

            return $request->expectsJson()
                ? response()->json(['message' => 'Tenant deletion could not be queued.', 'errors' => ['deletion' => [$e->getMessage()]]], 422)
                : back()->withErrors(['deletion' => $e->getMessage()]);
        }

        $message = $restoreStatus === 'failed'
            ? 'Cleanup of the failed tenant has been queued and will continue in the background.'
            : 'Permanent deletion has been queued and will continue in the background.';

        if ($request->expectsJson()) {
            return response()->json([
                'queued' => true,
                'tenant_id' => $tenantId,
                'deletion_record_id' => $history->getKey(),
                'status' => 'started',
                'progress' => 5,
                'message' => $message,
                'redirect' => route('central.tenants.show', $tenant),
            ], 202);
        }

        return redirect()->route('central.tenants.show', $tenant)->with('status', $message);
    }

    public function deletionStatus(string $tenantId): JsonResponse
    {
        $history = TenantDeletionRecord::query()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        if (! $history instanceof TenantDeletionRecord) {
            return $this->deletionStartingResponse($tenantId);
        }

        $status = (string) $history->status;
        $results = (array) $history->cleanup_results;
        $completed = in_array($status, ['completed', 'completed_with_warnings'], true);
        $failed = $status === 'failed';
        $tenant = Tenant::query()->find($tenantId);

        if ($completed && $tenant instanceof Tenant) {
            return $this->deletionStartingResponse($tenantId);
        }

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
            $history->update(['status' => 'failed', 'completed_at' => now()]);
            if ($tenant instanceof Tenant && $tenant->status === 'deleting') {
                $tenant->update(['status' => $this->restoreStatusFor($tenant)]);
            }

            return response()->json([
                'tenant_id' => $tenantId,
                'status' => 'failed',
                'progress' => 100,
                'message' => 'Background deletion stopped reporting progress before completion. Review the cleanup record, then retry permanent deletion.',
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

    private function deletionStartingResponse(string $tenantId): JsonResponse
    {
        return response()->json([
            'tenant_id' => $tenantId,
            'status' => 'starting',
            'progress' => 5,
            'message' => 'Preparing permanent deletion…',
            'completed' => false,
            'failed' => false,
        ]);
    }

    private function deletionIsStale(TenantDeletionRecord $history): bool
    {
        if ((string) $history->status !== 'started' || $history->updated_at === null) {
            return false;
        }

        return $history->updated_at->lt(now()->subMinutes(self::STALE_DELETION_MINUTES));
    }

    private function restoreStatusFor(Tenant $tenant): string
    {
        return $tenant->provisioning_status === 'failed' ? 'failed' : 'suspended';
    }

    /** @param array<string, mixed> $results @return array{int, string} */
    private function deletionProgress(TenantDeletionRecord $history, array $results): array
    {
        if ($results === []) {
            return [8, 'Queued for background deletion…'];
        }

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
