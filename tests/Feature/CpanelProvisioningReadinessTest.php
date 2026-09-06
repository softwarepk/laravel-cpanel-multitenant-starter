<?php

use App\Services\CpanelProvisioningReadiness;

it('treats the local sqlite tenant driver as unavailable for cpanel provisioning', function (): void {
    config(['database.connections.tenant_template.driver' => 'sqlite']);

    $readiness = app(CpanelProvisioningReadiness::class);

    expect($readiness->isReady())->toBeFalse()
        ->and($readiness->missingRequirements())->toContain('TENANT_DB_DRIVER must be mysql or mariadb');
});

it('recognizes a complete production cpanel provisioning configuration', function (): void {
    config([
        'database.connections.tenant_template.driver' => 'mysql',
        'database.connections.tenant_template.username' => 'account_app',
        'database.connections.tenant_template.password' => 'secret',
        'central.platform.domain' => 'tenants.example.com',
        'central.platform.document_root' => '/home/account/app/public',
        'central.cpanel.api_host' => 'server.example.com',
        'central.cpanel.api_user' => 'account',
        'central.cpanel.api_token' => 'token',
    ]);

    $readiness = app(CpanelProvisioningReadiness::class);

    expect($readiness->isReady())->toBeTrue()
        ->and($readiness->missingRequirements())->not->toContain('CPANEL_DB_USER');
});
