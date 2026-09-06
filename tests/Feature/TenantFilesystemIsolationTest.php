<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('changes local storage root when tenant context changes', function (): void {
    $firstTenant = $this->testTenant;
    $firstRoot = Storage::disk('local')->path('');

    $firstConnection = DB::connection();
    while ($firstConnection->transactionLevel() > 0) {
        $firstConnection->rollBack();
    }
    tenancy()->end();

    $secondDatabaseName = 'test-storage-second.sqlite';
    $secondDatabasePath = database_path($secondDatabaseName);
    if (is_file($secondDatabasePath)) {
        @unlink($secondDatabasePath);
    }
    touch($secondDatabasePath);
    config(['database.connections.tenant_template.database' => $secondDatabasePath]);

    $secondTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::create([
        'id' => 'storage-second',
        'name' => 'Storage Second',
        'status' => 'active',
        'provisioning_status' => 'active',
        'database_name' => $secondDatabaseName,
    ]));
    $secondTenant->setInternal('db_name', $secondDatabaseName);
    $secondTenant->save();

    tenancy()->initialize($secondTenant);

    try {
        $secondRoot = Storage::disk('local')->path('');

        expect($secondRoot)->not->toBe($firstRoot)
            ->and($firstRoot)->toContain('test-tenant')
            ->and($secondRoot)->toContain('storage-second');
    } finally {
        tenancy()->end();
        @unlink($secondDatabasePath);
        config(['database.connections.tenant_template.database' => database_path(static::sharedTenantDatabaseName())]);
        tenancy()->initialize($firstTenant);
        DB::connection()->beginTransaction();
    }
});
