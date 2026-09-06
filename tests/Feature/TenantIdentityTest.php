<?php

use App\Services\TenantDatabaseNamer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('generates distinct tenant database names for ids that normalize similarly', function (): void {
    config(['central.cpanel.tenant_database_prefix' => 'account_']);

    $namer = app(TenantDatabaseNamer::class);
    $first = $namer->forTenant('acme-us');
    $second = $namer->forTenant('acme--us');

    expect($first)->not->toBe($second)
        ->and($first)->toMatch('/^account_[A-Za-z0-9_]+$/')
        ->and($second)->toMatch('/^account_[A-Za-z0-9_]+$/')
        ->and(strlen($first))->toBeLessThanOrEqual(64)
        ->and(strlen($second))->toBeLessThanOrEqual(64)
        ->and($namer->forTenant('acme-us'))->toBe($first);
});

it('keeps generated tenant database names within the mysql identifier limit', function (): void {
    config(['central.cpanel.tenant_database_prefix' => str_repeat('p', 20).'_']);

    $name = app(TenantDatabaseNamer::class)->forTenant('a-very-long-tenant-id-with-hyphens');

    expect(strlen($name))->toBeLessThanOrEqual(64)
        ->and($name)->toMatch('/^[A-Za-z0-9_]+$/');
});

it('enforces unique persisted tenant database names in the central database', function (): void {
    $central = (string) config('tenancy.database.central_connection');

    DB::connection($central)->table('tenants')->insert([
        'id' => 'identity-one',
        'database_name' => 'account_identity_shared',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::connection($central)->table('tenants')->insert([
        'id' => 'identity-two',
        'database_name' => 'account_identity_shared',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
