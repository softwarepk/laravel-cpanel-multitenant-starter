<?php

use App\Http\Controllers\CentralTenantLifecycleController;
use App\Models\TenantDeletionRecord;

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

    $controller = app(CentralTenantLifecycleController::class);
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

    $data = app(CentralTenantLifecycleController::class)
        ->deletionStatus('deleted-tenant')
        ->getData(true);

    expect($data['status'])->toBe('completed');
    expect($data['progress'])->toBe(100);
    expect($data['completed'])->toBeTrue();
    expect($data['failed'])->toBeFalse();
    expect($data['redirect'])->toContain('/central/tenants');
});

it('releases a stale started deletion instead of leaving the page blocked forever', function (): void {
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
    $history->updated_at = now()->subMinutes(10);
    $history->save();

    $data = app(CentralTenantLifecycleController::class)
        ->deletionStatus('stale-tenant')
        ->getData(true);

    expect($data['status'])->toBe('stale');
    expect($data['failed'])->toBeTrue();
    expect($data['completed'])->toBeFalse();
    expect($data['message'])->toContain('may have stopped');
});
