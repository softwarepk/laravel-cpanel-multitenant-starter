<?php

use App\Actions\Tenants\DeleteTenant;
use App\Contracts\CustomDomainDeprovisioner;
use App\Contracts\PlatformDomainDeprovisioner;
use App\Contracts\TenantDatabaseProvisioner;
use App\Jobs\DeleteTenantJob;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use RuntimeException;

it('does not let an older deletion failure restore lifecycle state owned by a newer attempt', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'deleting', 'provisioning_status' => 'active']);

    $oldHistory = TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => 'Old Generation',
        'database_name' => $tenant->database_name,
        'platform_domain' => $this->testTenantDomain,
        'custom_domains' => [],
        'status' => 'failed',
        'cleanup_results' => [],
        'completed_at' => now(),
    ]);

    $newHistory = TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => 'New Generation',
        'database_name' => $tenant->database_name,
        'platform_domain' => $this->testTenantDomain,
        'custom_domains' => [],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    (new DeleteTenantJob(
        (string) $tenant->getTenantKey(),
        (int) $oldHistory->getKey(),
        null,
        'suspended',
    ))->failed(new RuntimeException('Old deletion stopped.'));

    expect($tenant->refresh()->status)->toBe('deleting');
    expect($newHistory->refresh()->status)->toBe('started');
});

it('restores lifecycle state when the current deletion job fails', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'deleting', 'provisioning_status' => 'active']);

    $history = TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => $tenant->name,
        'database_name' => $tenant->database_name,
        'platform_domain' => $this->testTenantDomain,
        'custom_domains' => [],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    (new DeleteTenantJob(
        (string) $tenant->getTenantKey(),
        (int) $history->getKey(),
        null,
        'suspended',
    ))->failed(new RuntimeException('Current deletion stopped.'));

    expect($history->refresh()->status)->toBe('failed');
    expect($tenant->refresh()->status)->toBe('suspended');
});

it('stops a retired deletion before entering later destructive stages', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'deleting', 'provisioning_status' => 'active']);
    $tenant->domains()->create([
        'domain' => 'custom.example.test',
        'type' => 'custom',
        'status' => 'active',
        'is_primary' => false,
    ]);

    $history = TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => $tenant->name,
        'database_name' => $tenant->database_name,
        'platform_domain' => $this->testTenantDomain,
        'custom_domains' => ['custom.example.test'],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    $database = new class implements TenantDatabaseProvisioner
    {
        public bool $deleteCalled = false;

        public function ensureDatabaseReady(string $databaseName): void {}

        public function deleteDatabase(string $databaseName): void
        {
            $this->deleteCalled = true;
        }
    };

    $platform = new class($history) implements PlatformDomainDeprovisioner
    {
        public bool $called = false;

        public function __construct(private readonly TenantDeletionRecord $history) {}

        public function deletePlatformDomain(string $domain): void
        {
            $this->called = true;
            $this->history->update(['status' => 'failed', 'completed_at' => now()]);
        }
    };

    $custom = new class implements CustomDomainDeprovisioner
    {
        public bool $called = false;

        public function deleteCustomDomain(string $domain): void
        {
            $this->called = true;
        }
    };

    $action = new DeleteTenant($database, $platform, $custom, app(CentralAuditLogger::class));

    expect(fn () => $action->handle($tenant, history: $history))
        ->toThrow(RuntimeException::class, 'Deletion history is no longer active.');

    expect($platform->called)->toBeTrue();
    expect($custom->called)->toBeFalse();
    expect($database->deleteCalled)->toBeFalse();
});
