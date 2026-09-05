<?php

namespace App\Http\Controllers;

use App\Models\CentralAuditLog;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\View\View;

class CentralDashboardController extends Controller
{
    public function index(): View
    {
        return view('central.dashboard', [
            'totalTenants' => Tenant::query()->count(),
            'activeTenants' => Tenant::query()->where('status', 'active')->where('provisioning_status', 'active')->count(),
            'suspendedTenants' => Tenant::query()->where('status', 'suspended')->count(),
            'attentionTenants' => Tenant::query()->where(fn ($query) => $query->where('status', 'failed')->orWhere('provisioning_status', 'failed'))->count(),
            'activeCustomDomains' => Domain::query()->where('type', 'custom')->where('status', 'active')->count(),
            'pendingCustomDomains' => Domain::query()->where('type', 'custom')->where('status', 'pending')->count(),
            'recentTenants' => Tenant::query()->with('domains')->latest('created_at')->limit(6)->get(),
            'recentActivity' => CentralAuditLog::query()->with('centralAdmin')->latest('created_at')->limit(8)->get(),
        ]);
    }
}
