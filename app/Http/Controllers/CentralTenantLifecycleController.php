<?php

namespace App\Http\Controllers;

use App\Actions\Tenants\DeleteTenant;
use App\Models\CentralAdmin;
use App\Models\Tenant;
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

    public function destroy(Request $request, Tenant $tenant, DeleteTenant $deleteTenant, CentralAuditLogger $audit): JsonResponse|RedirectResponse
    {
        abort_unless($tenant->status === 'suspended', 409, 'Suspend the tenant before permanently deleting it.');
        $tenantId = (string) $tenant->getTenantKey();
        $validated = $request->validate(['tenant_id_confirmation' => ['required', 'string'], 'current_password' => ['required', 'string']]);
        if (! hash_equals($tenantId, trim((string) $validated['tenant_id_confirmation']))) throw ValidationException::withMessages(['tenant_id_confirmation' => 'The tenant ID confirmation does not match.']);
        $admin = Auth::guard('central')->user();
        if (! $admin instanceof CentralAdmin || ! Hash::check($validated['current_password'], $admin->password)) throw ValidationException::withMessages(['current_password' => 'The Control Center password is incorrect.']);
        try { $deleteTenant->handle($tenant); } catch (Throwable $e) {
            report($e);
            $audit->log('tenant.deletion_failed', 'Permanent tenant deletion failed.', tenantId: $tenantId, context: ['error' => $e->getMessage()], request: $request);
            return $request->expectsJson() ? response()->json(['message' => 'Tenant deletion failed.', 'errors' => ['deletion' => [$e->getMessage()]]], 422) : back()->withErrors(['deletion' => $e->getMessage()]);
        }
        if ($request->expectsJson()) return response()->json(['deleted' => true, 'tenant_id' => $tenantId, 'message' => 'Tenant permanently deleted.', 'redirect' => route('central.tenants.index')]);
        return redirect()->route('central.tenants.index')->with('status', "Tenant {$tenantId} was permanently deleted.");
    }
}
