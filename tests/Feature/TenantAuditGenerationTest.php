<?php

use App\Http\Controllers\CentralTenantPagesController;
use App\Models\CentralAuditLog;
use Illuminate\Support\Facades\DB;

it('shows only audit activity from the current tenant generation on the tenant detail page', function (): void {
    $tenant = $this->testTenant;
    $central = (string) config('tenancy.database.central_connection');

    $old = CentralAuditLog::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'action' => 'tenant.old_generation_event',
        'description' => 'Old generation event.',
    ]);
    DB::connection($central)->table('central_audit_logs')->where('id', $old->getKey())->update([
        'created_at' => $tenant->created_at->copy()->subMinute(),
    ]);

    CentralAuditLog::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'action' => 'tenant.current_generation_event',
        'description' => 'Current generation event.',
    ]);

    $view = app(CentralTenantPagesController::class)->show($tenant);
    $actions = $view->getData()['auditLogs']->pluck('action')->all();

    expect($actions)->toContain('tenant.current_generation_event');
    expect($actions)->not->toContain('tenant.old_generation_event');
});
