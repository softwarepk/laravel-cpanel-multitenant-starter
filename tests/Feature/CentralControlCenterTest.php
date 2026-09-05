<?php

use App\Models\CentralAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('requires central administrator authentication for the control center', function (): void {
    $this->callThroughTenantResolver(
        'GET',
        '/central',
        server: [
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
        ],
    )->assertRedirectToRoute('central.login');
});

it('allows a central administrator to authenticate independently of tenant users', function (): void {
    $tenant = $this->testTenant;
    $connection = DB::connection();

    while ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }

    tenancy()->end();

    CentralAdmin::query()->create([
        'name' => 'Platform Administrator',
        'email' => 'platform@example.test',
        'password' => Hash::make('secret-password'),
    ]);

    try {
        $this->callThroughTenantResolver(
            'POST',
            '/central/login',
            server: [
                'HTTP_HOST' => 'localhost',
                'SERVER_NAME' => 'localhost',
            ],
            parameters: [
                'email' => 'platform@example.test',
                'password' => 'secret-password',
            ],
        )->assertRedirectToRoute('central.home');
    } finally {
        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
            DB::connection()->beginTransaction();
        }
    }
});
