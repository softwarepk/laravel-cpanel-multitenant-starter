<?php

namespace App\Http\Controllers;

use App\Models\CentralAuditLog;
use App\Models\Tenant;
use App\Services\CentralSettings;
use Illuminate\View\View;

class CentralTenantPagesController extends Controller
{
    public function index(): View
    {
        return view('central.tenants.index', ['tenants' => Tenant::query()->with('domains')->orderBy('name')->orderBy('id')->get(), 'platformDomain' => (string) config('central.platform.domain')]);
    }

    public function create(CentralSettings $settings): View
    {
        return view('central.tenants.create', ['platformDomain' => (string) config('central.platform.domain'), 'passwordMinimumLength' => $settings->passwordMinimumLength()]);
    }

    public function show(Tenant $tenant, CentralSettings $settings): View
    {
        $tenant->load('domains');

        return view('central.tenants.show', [
            'tenant' => $tenant,
            'auditLogs' => CentralAuditLog::query()->with('centralAdmin')->where('tenant_id', (string) $tenant->getTenantKey())->latest('created_at')->limit(20)->get(),
            'platformDomain' => (string) config('central.platform.domain'),
            'customDomainDnsTarget' => (string) config('central.custom_domain.dns_target'),
            'platformDocumentRoot' => (string) config('central.platform.document_root'),
            'passwordMinimumLength' => $settings->passwordMinimumLength(),
        ]);
    }
}
