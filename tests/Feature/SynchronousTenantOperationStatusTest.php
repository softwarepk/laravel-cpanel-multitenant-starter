<?php

use App\Actions\Tenants\DeleteTenant;
use App\Actions\Tenants\ProvisionTenant;
use App\Http\Controllers\CentralTenantController;
use App\Http\Controllers\CentralTenantLifecycleController;
use App\Http\Controllers\CentralTenantResumeController;
use App\Http\Controllers\TenantDeletionStatusController;
use App\Models\CentralAdmin;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use App\Services\PlatformHttpsVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

it('marks deletion progress stale only after the shared operation window', function (): void {
    config(['central.lifecycle.operation_stale_after_seconds' => 300]);

    $record = TenantDeletionRecord::query()->create([
        'tenant_id' => 'delete-stale-test',
        'tenant_name' => 'Delete Stale Test',
        'database_name' => 'test_delete_stale',
        'platform_domain' => 'delete-stale-test.tenants.example.test',
        'custom_domains' => [],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    $record->timestamps = false;
    $record->updated_at = now()->subSeconds(301);
    $record->saveQuietly();

    $data = app(TenantDeletionStatusController::class)('delete-stale-test')->getData(true);

    expect($data['stale'])->toBeTrue()
        ->and($data['message'])->toContain('has not advanced recently');
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

it('does not let the HTTPS recheck reactivate a suspended tenant', function (): void {
    $tenant = $this->testTenant;
    $tenant->update([
        'status' => 'suspended',
        'provisioning_status' => 'active',
        'suspended_at' => now(),
    ]);

    $https = Mockery::mock(PlatformHttpsVerifier::class);
    $https->shouldNotReceive('isReady');
    $audit = Mockery::mock(CentralAuditLogger::class);

    try {
        app(CentralTenantController::class)->checkPlatformHttps(
            Request::create('/central/tenants/test-tenant/https/check', 'POST'),
            $tenant,
            $https,
            $audit,
        );
        $this->fail('Expected suspended tenant HTTPS recheck to be rejected.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});

it('prevents a second provisioning request while the saved operation is still fresh', function (): void {
    config(['central.lifecycle.operation_stale_after_seconds' => 300]);

    $tenant = $this->testTenant;
    $tenant->update([
        'status' => 'provisioning',
        'provisioning_status' => 'database',
    ]);

    $provision = Mockery::mock(ProvisionTenant::class);
    $provision->shouldNotReceive('handle');
    $audit = Mockery::mock(CentralAuditLogger::class);

    try {
        app(CentralTenantResumeController::class)(
            Request::create('/central/tenants/test-tenant/retry', 'POST'),
            $tenant,
            $provision,
            $audit,
        );
        $this->fail('Expected fresh provisioning retry to be rejected.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409)
            ->and($e->getMessage())->toContain('reported progress recently');
    }
});

it('prevents a second deletion request while the saved cleanup is still fresh', function (): void {
    config(['central.lifecycle.operation_stale_after_seconds' => 300]);

    $tenant = $this->testTenant;
    $tenant->update([
        'status' => 'suspended',
        'provisioning_status' => 'active',
        'suspended_at' => now(),
    ]);

    TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => $tenant->name,
        'database_name' => $tenant->database_name,
        'platform_domain' => $tenant->domains()->where('type', 'platform')->value('domain'),
        'custom_domains' => [],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    $admin = CentralAdmin::query()->create([
        'name' => 'Delete Guard Admin',
        'email' => 'delete-guard@example.test',
        'password' => Hash::make('delete-guard-password'),
    ]);
    Auth::guard('central')->setUser($admin);

    $deleteTenant = Mockery::mock(DeleteTenant::class);
    $deleteTenant->shouldNotReceive('handle');
    $audit = Mockery::mock(CentralAuditLogger::class);

    $request = Request::create('/central/tenants/test-tenant', 'DELETE', [
        'tenant_id_confirmation' => (string) $tenant->getTenantKey(),
        'current_password' => 'delete-guard-password',
    ]);

    try {
        app(CentralTenantLifecycleController::class)->destroy($request, $tenant, $deleteTenant, $audit);
        $this->fail('Expected fresh deletion retry to be rejected.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409)
            ->and($e->getMessage())->toContain('reported progress recently');
    } finally {
        Auth::guard('central')->logout();
    }
});

it('keeps lifecycle confirmations inside the control center UI and only one retry controller', function (): void {
    $tenantView = file_get_contents(resource_path('views/central/tenants/show.blade.php'));
    $suspendComponent = file_get_contents(resource_path('views/components/central/tenant-suspend-confirmation.blade.php'));
    $tenantController = file_get_contents(app_path('Http/Controllers/CentralTenantController.php'));

    expect($tenantView)->not->toBeFalse()
        ->and($tenantView)->not->toContain('window.confirm')
        ->and($suspendComponent)->not->toBeFalse()
        ->and($suspendComponent)->toContain('<dialog')
        ->and($tenantController)->not->toBeFalse()
        ->and($tenantController)->not->toContain('public function retry(');
});
