<?php

use App\Contracts\CustomDomainDeprovisioner;
use App\Contracts\CustomDomainProvisioner;
use App\Contracts\CustomDomainVerifier;
use App\Http\Controllers\CentralTenantController;
use App\Http\Controllers\CentralTenantPagesController;
use App\Http\Controllers\TenantDeletionStatusController;
use App\Models\CentralAuditLog;
use App\Models\Domain;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('blocks every custom-domain mutation while deletion cleanup is unresolved', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'suspended', 'provisioning_status' => 'active', 'suspended_at' => now()]);
    $custom = $tenant->domains()->create([
        'domain' => 'portal.example.test',
        'type' => 'custom',
        'status' => 'active',
        'is_primary' => false,
    ]);

    TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => $tenant->name,
        'database_name' => $tenant->database_name,
        'platform_domain' => $this->testTenantDomain,
        'custom_domains' => [$custom->domain],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    $provisioner = new class implements CustomDomainProvisioner
    {
        public bool $called = false;

        public function ensureCustomDomainReady(string $domain): void
        {
            $this->called = true;
        }
    };
    $verifier = new class implements CustomDomainVerifier
    {
        public bool $called = false;

        public function verify(string $domain): array
        {
            $this->called = true;

            return ['dns' => true, 'cpanel' => true, 'ssl' => true, 'errors' => []];
        }
    };
    $deprovisioner = new class implements CustomDomainDeprovisioner
    {
        public bool $called = false;

        public function deleteCustomDomain(string $domain): void
        {
            $this->called = true;
        }
    };

    $controller = app(CentralTenantController::class);
    $audit = app(CentralAuditLogger::class);
    $assertBlocked = function (callable $operation): void {
        try {
            $operation();
            $this->fail('Expected unresolved deletion cleanup to block tenant configuration.');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(409);
        }
    };

    $assertBlocked(fn () => $controller->addCustomDomain(
        Request::create('/central/tenants/test-tenant/domains', 'POST', ['domain' => 'new.example.test']),
        $tenant,
        $provisioner,
        $audit,
    ));
    $assertBlocked(fn () => $controller->verifyCustomDomain(
        Request::create('/central/tenants/test-tenant/domains/1/verify', 'POST'),
        $tenant,
        $custom,
        $provisioner,
        $verifier,
        $audit,
    ));
    $assertBlocked(fn () => $controller->makePrimaryDomain(
        Request::create('/central/tenants/test-tenant/domains/1/primary', 'POST'),
        $tenant,
        $custom,
        $audit,
    ));
    $assertBlocked(fn () => $controller->deleteCustomDomain(
        Request::create('/central/tenants/test-tenant/domains/1', 'DELETE'),
        $tenant,
        $custom,
        $deprovisioner,
        $audit,
    ));

    expect($provisioner->called)->toBeFalse()
        ->and($verifier->called)->toBeFalse()
        ->and($deprovisioner->called)->toBeFalse()
        ->and(Domain::on(config('tenancy.database.central_connection'))
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->where('domain', 'new.example.test')
            ->exists())->toBeFalse()
        ->and($custom->refresh()->is_primary)->toBeFalse();
});

it('still allows central domain management for a clean suspended tenant', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'suspended', 'provisioning_status' => 'active', 'suspended_at' => now()]);

    $provisioner = new class implements CustomDomainProvisioner
    {
        public bool $called = false;

        public function ensureCustomDomainReady(string $domain): void
        {
            $this->called = true;
        }
    };

    app(CentralTenantController::class)->addCustomDomain(
        Request::create('/central/tenants/test-tenant/domains', 'POST', ['domain' => 'portal.example.test']),
        $tenant,
        $provisioner,
        app(CentralAuditLogger::class),
    );

    expect($provisioner->called)->toBeTrue()
        ->and(Domain::on(config('tenancy.database.central_connection'))
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->where('domain', 'portal.example.test')
            ->exists())->toBeTrue();
});

it('shows only current-generation audit activity on the tenant detail page', function (): void {
    $tenant = $this->testTenant->refresh();

    $old = CentralAuditLog::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'action' => 'tenant.old_generation',
        'description' => 'Old tenant generation event.',
        'context' => [],
    ]);
    $old->timestamps = false;
    $old->created_at = $tenant->created_at->copy()->subDay();
    $old->saveQuietly();

    CentralAuditLog::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'action' => 'tenant.current_generation',
        'description' => 'Current tenant generation event.',
        'context' => [],
    ]);

    $view = app(CentralTenantPagesController::class)->show($tenant);
    $actions = $view->getData()['auditLogs']->pluck('action')->all();

    expect($actions)->toContain('tenant.current_generation')
        ->and($actions)->not->toContain('tenant.old_generation');
});

it('does not mistake a prior clean deletion for a new deletion operation after tenant ID reuse', function (): void {
    $tenant = $this->testTenant->refresh();

    $old = TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => 'Prior Tenant Generation',
        'database_name' => 'prior_database',
        'platform_domain' => 'prior.example.test',
        'custom_domains' => [],
        'status' => 'completed',
        'cleanup_results' => [
            'central_record' => ['status' => 'deleted', 'target' => (string) $tenant->getTenantKey()],
        ],
        'completed_at' => $tenant->created_at->copy()->subMinute(),
    ]);

    $starting = app(TenantDeletionStatusController::class)((string) $tenant->getTenantKey())->getData(true);

    expect($starting['status'])->toBe('starting')
        ->and($starting)->not->toHaveKey('record_id');

    $current = TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => $tenant->name,
        'database_name' => $tenant->database_name,
        'platform_domain' => $this->testTenantDomain,
        'custom_domains' => [],
        'status' => 'started',
        'cleanup_results' => [],
    ]);

    $running = app(TenantDeletionStatusController::class)((string) $tenant->getTenantKey())->getData(true);

    expect($running['status'])->toBe('started')
        ->and($running['record_id'])->toBe($current->getKey())
        ->and($running['record_id'])->not->toBe($old->getKey());
});
