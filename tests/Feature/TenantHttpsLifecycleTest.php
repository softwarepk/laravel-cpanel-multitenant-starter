<?php

use App\Http\Controllers\CentralTenantController;
use App\Services\CentralAuditLogger;
use App\Services\PlatformHttpsVerifier;
use Illuminate\Http\Request;

it('does not downgrade an active tenant or platform domain when HTTPS recheck is inconclusive', function (): void {
    $tenant = $this->testTenant;
    $platform = $tenant->domains()->where('type', 'platform')->firstOrFail();
    $platform->update(['status' => 'active', 'ssl_verified_at' => null]);

    $verifier = new class extends PlatformHttpsVerifier
    {
        public function isReady(string $domain): bool
        {
            return false;
        }
    };

    $request = Request::create('/central/tenants/test-tenant/https/check', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = app(CentralTenantController::class)->checkPlatformHttps(
        $request,
        $tenant,
        $verifier,
        app(CentralAuditLogger::class),
    );

    expect($response->getStatusCode())->toBe(202);
    expect($tenant->refresh()->status)->toBe('active');
    expect($tenant->provisioning_status)->toBe('active');
    expect($platform->refresh()->status)->toBe('active');
    expect($platform->ssl_verified_at)->toBeNull();
});

it('records trusted HTTPS for an active tenant without changing lifecycle state', function (): void {
    $tenant = $this->testTenant;
    $platform = $tenant->domains()->where('type', 'platform')->firstOrFail();
    $platform->update(['status' => 'active', 'ssl_verified_at' => null]);

    $verifier = new class extends PlatformHttpsVerifier
    {
        public function isReady(string $domain): bool
        {
            return true;
        }
    };

    $request = Request::create('/central/tenants/test-tenant/https/check', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = app(CentralTenantController::class)->checkPlatformHttps(
        $request,
        $tenant,
        $verifier,
        app(CentralAuditLogger::class),
    );

    expect($response->getStatusCode())->toBe(200);
    expect($tenant->refresh()->status)->toBe('active');
    expect($tenant->provisioning_status)->toBe('active');
    expect($platform->refresh()->status)->toBe('active');
    expect($platform->ssl_verified_at)->not->toBeNull();
});
