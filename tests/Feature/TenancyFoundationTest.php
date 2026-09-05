<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

it('serves the tenant application through a registered tenant hostname', function (): void {
    expect(tenancy()->initialized)->toBeTrue()
        ->and(DB::connection()->getDatabaseName())->toContain('test-tenant');

    $this->callThroughTenantResolver('GET', '/')
        ->assertRedirectToRoute('login');
});

it('does not expose the tenant application on a central hostname', function (): void {
    URL::forceRootUrl(null);

    try {
        $this->callThroughTenantResolver(
            'GET',
            '/',
            server: [
                'HTTP_HOST' => 'localhost',
                'SERVER_NAME' => 'localhost',
            ],
        )->assertNotFound();
    } finally {
        URL::forceRootUrl('http://'.$this->testTenantDomain);
    }
});

it('keeps tenant users out of the central database', function (): void {
    User::factory()->create([
        'email' => 'tenant-user@example.test',
    ]);

    $tenant = $this->testTenant;
    $connection = DB::connection();

    while ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }

    tenancy()->end();

    try {
        expect(DB::connection()->getDatabaseName())->toBe(':memory:')
            ->and(Schema::hasTable('users'))->toBeFalse();
    } finally {
        tenancy()->initialize($tenant);
        DB::connection()->beginTransaction();
    }
});

it('keeps shared application assets on the normal public asset path', function (): void {
    expect(config('tenancy.filesystem.asset_helper_tenancy'))->toBeFalse();

    $assetUrl = asset('build/assets/app-test.css');

    expect($assetUrl)
        ->toContain('/build/assets/app-test.css')
        ->not->toContain('/tenancy/assets/');
});
