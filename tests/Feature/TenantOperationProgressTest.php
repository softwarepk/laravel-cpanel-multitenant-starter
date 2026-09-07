<?php

use App\Actions\Tenants\DeleteTenant;
use App\Http\Controllers\QueuedTenantDeletionController;
use App\Http\Controllers\QueuedTenantProvisioningController;
use App\Models\TenantDeletionRecord;

it('reports queued deletion before background cleanup starts', function (): void {
    TenantDeletionRecord::query()->create([
        'tenant_id' => 'queued-tenant',
        'tenant_name' => 'Queued Tenant',
        'database_name' => 'tenant_queued',
        'platform_domain' => 'queued.example.test',
        'custom_domains' => [],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    $data = app(QueuedTenantDeletionController::class)
        ->deletionStatus('queued-tenant')
        ->getData(true);

    expect($data['status'])->toBe('started');
    expect($data['progress'])->toBe(8);
    expect($data['message'])->toBe('Queued for background deletion…');
    expect($data['completed'])->toBeFalse();
    expect($data['failed'])->toBeFalse();
});

it('reports deletion progress from durable cleanup history', function (): void {
    $history = TenantDeletionRecord::query()->create([
        'tenant_id' => 'progress-tenant',
        'tenant_name' => 'Progress Tenant',
        'database_name' => 'tenant_progress',
        'platform_domain' => 'progress.example.test',
        'custom_domains' => ['portal.example.test'],
        'status' => 'started',
        'cleanup_results' => [
            'platform_domain' => ['status' => 'deleted', 'target' => 'progress.example.test'],
        ],
    ]);

    $controller = app(QueuedTenantDeletionController::class);
    $data = $controller->deletionStatus('progress-tenant')->getData(true);

    expect($data['status'])->toBe('started');
    expect($data['progress'])->toBe(25);
    expect($data['message'])->toBe('Removing custom domain portal.example.test…');
    expect($data['completed'])->toBeFalse();
    expect($data['failed'])->toBeFalse();

    $history->update([
        'cleanup_results' => [
            'platform_domain' => ['status' => 'deleted', 'target' => 'progress.example.test'],
            'custom_domain:portal.example.test' => ['status' => 'deleted', 'target' => 'portal.example.test'],
            'storage' => ['status' => 'not_present', 'target' => '/tmp/progress-tenant'],
        ],
    ]);

    $data = $controller->deletionStatus('progress-tenant')->getData(true);

    expect($data['progress'])->toBe(72);
    expect($data['message'])->toBe('Removing the tenant database…');
});

it('reports completed deletion progress after the tenant record is gone', function (): void {
    TenantDeletionRecord::query()->create([
        'tenant_id' => 'deleted-tenant',
        'tenant_name' => 'Deleted Tenant',
        'database_name' => 'tenant_deleted',
        'platform_domain' => 'deleted.example.test',
        'custom_domains' => [],
        'status' => 'completed',
        'cleanup_results' => [
            'platform_domain' => ['status' => 'deleted', 'target' => 'deleted.example.test'],
            'storage' => ['status' => 'not_present', 'target' => '/tmp/deleted-tenant'],
            'database' => ['status' => 'deleted', 'target' => 'tenant_deleted'],
            'central_record' => ['status' => 'deleted', 'target' => 'deleted-tenant'],
        ],
        'completed_at' => now(),
    ]);

    $data = app(QueuedTenantDeletionController::class)
        ->deletionStatus('deleted-tenant')
        ->getData(true);

    expect($data['status'])->toBe('completed');
    expect($data['progress'])->toBe(100);
    expect($data['completed'])->toBeTrue();
    expect($data['failed'])->toBeFalse();
    expect($data['redirect'])->toContain('/central/tenants');
});

it('does not confuse an older clean deletion with deletion of a recreated tenant', function (): void {
    TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $this->testTenant->getTenantKey(),
        'tenant_name' => 'Previous Tenant Generation',
        'database_name' => (string) $this->testTenant->database_name,
        'platform_domain' => 'tenant.localhost',
        'custom_domains' => [],
        'status' => 'completed',
        'cleanup_results' => [
            'platform_domain' => ['status' => 'deleted', 'target' => 'tenant.localhost'],
            'storage' => ['status' => 'not_present', 'target' => '/tmp/test-tenant'],
            'database' => ['status' => 'deleted', 'target' => (string) $this->testTenant->database_name],
            'central_record' => ['status' => 'deleted', 'target' => (string) $this->testTenant->getTenantKey()],
        ],
        'completed_at' => now(),
    ]);

    $data = app(QueuedTenantDeletionController::class)
        ->deletionStatus((string) $this->testTenant->getTenantKey())
        ->getData(true);

    expect($data['status'])->toBe('starting');
    expect($data['completed'])->toBeFalse();
    expect($data['failed'])->toBeFalse();
});

it('allows identity reuse only after a clean completed deletion', function (): void {
    $clean = new TenantDeletionRecord([
        'status' => 'completed',
        'cleanup_results' => [
            'database' => ['status' => 'deleted', 'target' => 'tenant_clean'],
        ],
    ]);
    $warning = new TenantDeletionRecord([
        'status' => 'completed_with_warnings',
        'cleanup_results' => [
            'database' => ['status' => 'failed', 'target' => 'tenant_warning', 'error' => 'cleanup failed'],
        ],
    ]);
    $failed = new TenantDeletionRecord(['status' => 'failed', 'cleanup_results' => []]);
    $started = new TenantDeletionRecord(['status' => 'started', 'cleanup_results' => []]);

    expect($clean->blocksIdentityReuse())->toBeFalse();
    expect($warning->blocksIdentityReuse())->toBeTrue();
    expect($failed->blocksIdentityReuse())->toBeTrue();
    expect($started->blocksIdentityReuse())->toBeTrue();
});

it('marks a stale queued deletion failed instead of leaving the page blocked forever', function (): void {
    $history = TenantDeletionRecord::query()->create([
        'tenant_id' => 'stale-tenant',
        'tenant_name' => 'Stale Tenant',
        'database_name' => 'tenant_stale',
        'platform_domain' => 'stale.example.test',
        'custom_domains' => [],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    $history->timestamps = false;
    $history->updated_at = now()->subMinutes(15);
    $history->save();

    $data = app(QueuedTenantDeletionController::class)
        ->deletionStatus('stale-tenant')
        ->getData(true);

    expect($data['status'])->toBe('failed');
    expect($data['failed'])->toBeTrue();
    expect($data['completed'])->toBeFalse();
    expect($data['message'])->toContain('stopped reporting progress');
    expect($history->refresh()->status)->toBe('failed');
});

it('marks stranded provisioning failed so it can be safely retried', function (): void {
    $tenant = $this->testTenant;
    $tenant->update([
        'status' => 'provisioning',
        'provisioning_status' => 'domain',
        'provisioning_error' => null,
    ]);
    $tenant->timestamps = false;
    $tenant->updated_at = now()->subMinutes(15);
    $tenant->save();

    $data = app(QueuedTenantProvisioningController::class)
        ->provisioningStatus((string) $tenant->getTenantKey())
        ->getData(true);

    expect($data['provisioning_status'])->toBe('failed');
    expect($data['message'])->toBe('Provisioning failed.');
    expect($tenant->refresh()->provisioning_error)->toContain('stopped reporting progress');
});

it('allows a tenant with failed provisioning to enter permanent cleanup', function (): void {
    $tenant = $this->testTenant;
    $tenant->update([
        'status' => 'failed',
        'provisioning_status' => 'failed',
        'provisioning_error' => 'Provisioning stopped before completion.',
    ]);

    $history = app(DeleteTenant::class)->begin($tenant);

    expect($history->tenant_id)->toBe((string) $tenant->getTenantKey());
    expect($history->status)->toBe('started');
    expect($history->platform_domain)->toBe($this->testTenantDomain);
});
