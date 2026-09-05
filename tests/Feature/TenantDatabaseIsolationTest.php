<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('keeps records isolated between tenant databases even when identifiers overlap', function (): void {
    $firstTenant = $this->testTenant;

    DB::table('users')->insert([
        'name' => 'Tenant A User',
        'email' => 'same@example.test',
        'password' => bcrypt('password'),
        'role' => 'user',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $firstDatabase = DB::connection()->getDatabaseName();
    $firstConnection = DB::connection();
    while ($firstConnection->transactionLevel() > 0) {
        $firstConnection->rollBack();
    }
    tenancy()->end();

    $secondDatabaseName = 'test-tenant-second.sqlite';
    $secondDatabasePath = database_path($secondDatabaseName);
    if (is_file($secondDatabasePath)) {
        @unlink($secondDatabasePath);
    }
    touch($secondDatabasePath);

    config(['database.connections.tenant_template.database' => $secondDatabasePath]);

    $secondTenant = Tenant::create([
        'id' => 'second-tenant',
        'name' => 'Second Tenant',
        'status' => 'active',
        'provisioning_status' => 'active',
        'database_name' => $secondDatabaseName,
        'tenancy_db_name' => $secondDatabaseName,
    ]);

    tenancy()->initialize($secondTenant);

    try {
        expect(DB::connection()->getDatabaseName())->not->toBe($firstDatabase)
            ->and(Schema::hasTable('users'))->toBeTrue()
            ->and(DB::table('users')->where('email', 'same@example.test')->exists())->toBeFalse();

        DB::table('users')->insert([
            'name' => 'Tenant B User',
            'email' => 'same@example.test',
            'password' => bcrypt('different-password'),
            'role' => 'administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(DB::table('users')->where('email', 'same@example.test')->value('name'))->toBe('Tenant B User');
    } finally {
        tenancy()->end();
        @unlink($secondDatabasePath);
        config(['database.connections.tenant_template.database' => database_path(static::sharedTenantDatabaseName())]);
        tenancy()->initialize($firstTenant);
        DB::connection()->beginTransaction();
    }
});
