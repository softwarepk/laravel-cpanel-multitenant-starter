<?php

use App\Http\Controllers\TenantDeletionStatusController;
use App\Models\TenantDeletionRecord;

it('reports durable progress for a synchronous tenant deletion', function (): void {
    TenantDeletionRecord::query()->create([
        'tenant_id' => 'delete-progress-test',
        'tenant_name' => 'Delete Progress Test',
        'database_name' => 'test_delete_progress',
        'platform_domain' => 'delete-progress-test.tenants.example.test',
        'custom_domains' => ['portal.example.test'],
        'status' => 'started',
        'cleanup_results' => [
            'platform_domain' => ['status' => 'deleted', 'target' => 'delete-progress-test.tenants.example.test'],
        ],
    ]);

    $response = app(TenantDeletionStatusController::class)('delete-progress-test');
    $data = $response->getData(true);

    expect($data['status'])->toBe('started')
        ->and($data['progress'])->toBeGreaterThan(8)
        ->and($data['message'])->toContain('custom domains')
        ->and($data['stale'])->toBeFalse();
});

it('reports completed synchronous deletion with the tenants redirect', function (): void {
    TenantDeletionRecord::query()->create([
        'tenant_id' => 'delete-complete-test',
        'tenant_name' => 'Delete Complete Test',
        'database_name' => 'test_delete_complete',
        'platform_domain' => 'delete-complete-test.tenants.example.test',
        'custom_domains' => [],
        'status' => 'completed',
        'cleanup_results' => [
            'platform_domain' => ['status' => 'deleted', 'target' => 'delete-complete-test.tenants.example.test'],
            'storage' => ['status' => 'not_present', 'target' => '/tmp/tenant-delete-complete-test'],
            'database' => ['status' => 'deleted', 'target' => 'test_delete_complete'],
            'central_record' => ['status' => 'deleted', 'target' => 'delete-complete-test'],
        ],
        'completed_at' => now(),
    ]);

    $response = app(TenantDeletionStatusController::class)('delete-complete-test');
    $data = $response->getData(true);

    expect($data['status'])->toBe('completed')
        ->and($data['progress'])->toBe(100)
        ->and($data['redirect'])->toBe(route('central.tenants.index'));
});

it('keeps lifecycle confirmations inside the control center UI', function (): void {
    $tenantView = file_get_contents(resource_path('views/central/tenants/show.blade.php'));
    $suspendComponent = file_get_contents(resource_path('views/components/central/tenant-suspend-confirmation.blade.php'));

    expect($tenantView)->not->toBeFalse()
        ->and($tenantView)->not->toContain('window.confirm')
        ->and($suspendComponent)->not->toBeFalse()
        ->and($suspendComponent)->toContain('<dialog');
});
