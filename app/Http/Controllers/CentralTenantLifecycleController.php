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

        $latestDeletion = TenantDeletionRecord::query()
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->latest('id')
            ->first();

        abort_if($latestDeletion instanceof TenantDeletionRecord && $latestDeletion->isUnresolved(), 409, 'This tenant has an unresolved deletion attempt. Retry or resolve deletion cleanup before reactivating it.');

        $tenant->update(['status' => 'active', 'suspended_at' => null]);
        $audit->log('tenant.activated', 'Tenant activated.', tenantId: (string) $tenant->getTenantKey(), request: $request);

        return back()->with('status', 'Tenant activated.');
    }

    public function destroy(Request $request, Tenant $tenant, DeleteTenant $deleteTenant, CentralAuditLogger $audit): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($tenant->status, ['suspended', 'failed'], true), 409, 'Suspend an active tenant before permanently deleting it. Failed provisioning tenants may be cleaned up directly.');
        $tenantId = (string) $tenant->getTenantKey();
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

        $latestDeletion = TenantDeletionRecord::query()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        if ($latestDeletion instanceof TenantDeletionRecord && (string) $latestDeletion->status === 'started') {
            if ($this->operationRecentlyAdvanced($latestDeletion)) {
                abort(409, 'Permanent deletion has reported progress recently and may still be running. Check deletion status and only retry after the saved operation has stopped advancing for five minutes.');
            }

            $latestDeletion->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);
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

    private function operationRecentlyAdvanced(TenantDeletionRecord $record): bool
    {
        if ($record->updated_at === null) {
            return false;
        }

        $seconds = max(1, (int) config('central.lifecycle.operation_stale_after_seconds', 300));

        return $record->updated_at->gt(now()->subSeconds($seconds));
    }
}
