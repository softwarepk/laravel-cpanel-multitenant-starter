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

        $unresolvedDeletion = TenantDeletionRecord::query()
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->whereIn('status', ['started', 'failed'])
            ->exists();

        abort_if($unresolvedDeletion, 409, 'This tenant has an unresolved deletion attempt. Retry or resolve deletion cleanup before reactivating it.');

        $tenant->update(['status' => 'active', 'suspended_at' => null]);
        $audit->log('tenant.activated', 'Tenant activated.', tenantId: (string) $tenant->getTenantKey(), request: $request);

        return back()->with('status', 'Tenant activated.');
    }

    public function destroy(Request $request, Tenant $tenant, DeleteTenant $deleteTenant, CentralAuditLogger $audit): JsonResponse|RedirectResponse
    {
        abort_unless($tenant->status === 'suspended', 409, 'Suspend the tenant before permanently deleting it.');
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
}
