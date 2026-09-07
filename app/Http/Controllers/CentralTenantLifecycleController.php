<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
}
