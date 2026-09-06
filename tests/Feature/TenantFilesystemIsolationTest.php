<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('changes tenant-aware storage roots when tenant context changes', function (): void {
    $firstTenant = $this->testTenant;
    $firstLocalRoot = Storage::disk('local')->path('');
    $firstPublicRoot = Storage::disk('public')->path('');

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
        $secondLocalRoot = Storage::disk('local')->path('');
        $secondPublicRoot = Storage::disk('public')->path('');

        expect($secondLocalRoot)->not->toBe($firstLocalRoot)
            ->and($secondPublicRoot)->not->toBe($firstPublicRoot)
            ->and($firstLocalRoot)->toContain('test-tenant')
            ->and($firstPublicRoot)->toContain('test-tenant')
            ->and($secondLocalRoot)->toContain('storage-second')
            ->and($secondPublicRoot)->toContain('storage-second');
    } finally {
        tenancy()->end();
        @unlink($secondDatabasePath);
        config(['database.connections.tenant_template.database' => database_path(static::sharedTenantDatabaseName())]);
        tenancy()->initialize($firstTenant);
        DB::connection()->beginTransaction();
    }
});
