<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('does not implicitly migrate infrastructure when a tenant model is created', function (): void {
    $firstTenant = $this->testTenant;
    $firstConnection = DB::connection();
    while ($firstConnection->transactionLevel() > 0) {
        $firstConnection->rollBack();
    }
    tenancy()->end();

    $databaseName = 'test-unprovisioned.sqlite';
    $databasePath = database_path($databaseName);
    if (is_file($databasePath)) {
        @unlink($databasePath);
    }
    touch($databasePath);
    config(['database.connections.tenant_template.database' => $databasePath]);

    $tenant = Tenant::create([
        'id' => 'unprovisioned',
        'name' => 'Unprovisioned Tenant',
        'status' => 'provisioning',
        'provisioning_status' => 'pending',
        'database_name' => $databaseName,
    ]);
    $tenant->setInternal('db_name', $databaseName);
    $tenant->save();

    tenancy()->initialize($tenant);

    try {
        expect(Schema::hasTable('users'))->toBeFalse()
            ->and(Schema::hasTable('cache'))->toBeFalse();
    } finally {
        tenancy()->end();
        @unlink($databasePath);
        config(['database.connections.tenant_template.database' => database_path(static::sharedTenantDatabaseName())]);
        tenancy()->initialize($firstTenant);
        DB::connection()->beginTransaction();
    }
});
