<?php

use App\Contracts\CustomDomainProvisioner;
use App\Http\Controllers\CentralTenantController;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use App\Services\PlatformHttpsVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('does not let the HTTPS check reactivate a suspended tenant', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'suspended', 'provisioning_status' => 'active', 'suspended_at' => now()]);
    $platform = $tenant->domains()->where('type', 'platform')->firstOrFail();
    $platform->update(['ssl_verified_at' => null]);

    $verifier = new class extends PlatformHttpsVerifier
    {
        public function isReady(string $domain): bool
        {
            return true;
        }
    };

    try {
        app(CentralTenantController::class)->checkPlatformHttps(
            Request::create('/central/tenants/test-tenant/https/check', 'POST'),
            $tenant,
            $verifier,
            app(CentralAuditLogger::class),
        );
        $this->fail('Expected the HTTPS lifecycle guard to reject a suspended tenant.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }

    expect($tenant->refresh()->status)->toBe('suspended');
    expect($platform->refresh()->ssl_verified_at)->toBeNull();
});

it('blocks custom-domain mutations while permanent deletion is running', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'deleting', 'provisioning_status' => 'active']);

    $provisioner = new class implements CustomDomainProvisioner
    {
        public bool $called = false;

        public function ensureCustomDomainReady(string $domain): void
        {
            $this->called = true;
        }
    };

    try {
        app(CentralTenantController::class)->addCustomDomain(
            Request::create('/central/tenants/test-tenant/domains', 'POST', ['domain' => 'portal.example.test']),
            $tenant,
            $provisioner,
            app(CentralAuditLogger::class),
        );
        $this->fail('Expected domain mutation to be rejected during deletion.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }

    expect($provisioner->called)->toBeFalse();
    expect($tenant->domains()->where('type', 'custom')->count())->toBe(0);
});

it('blocks tenant configuration while deletion cleanup is unresolved', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'suspended', 'provisioning_status' => 'active']);

    TenantDeletionRecord::query()->create([
        'tenant_id' => (string) $tenant->getTenantKey(),
        'tenant_name' => $tenant->name,
        'database_name' => $tenant->database_name,
        'platform_domain' => $this->testTenantDomain,
        'custom_domains' => [],
        'status' => 'failed',
        'cleanup_results' => [],
        'completed_at' => now(),
    ]);

    $provisioner = new class implements CustomDomainProvisioner
    {
        public bool $called = false;

        public function ensureCustomDomainReady(string $domain): void
        {
            $this->called = true;
        }
    };

    try {
        app(CentralTenantController::class)->addCustomDomain(
            Request::create('/central/tenants/test-tenant/domains', 'POST', ['domain' => 'portal.example.test']),
            $tenant,
            $provisioner,
            app(CentralAuditLogger::class),
        );
        $this->fail('Expected unresolved deletion cleanup to block tenant configuration.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }

    expect($provisioner->called)->toBeFalse();
});

it('still allows central domain management for a cleanly suspended tenant', function (): void {
    $tenant = $this->testTenant;
    $tenant->update(['status' => 'suspended', 'provisioning_status' => 'active']);

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

    expect($provisioner->called)->toBeTrue();
    expect($tenant->domains()->where('domain', 'portal.example.test')->exists())->toBeTrue();
});
