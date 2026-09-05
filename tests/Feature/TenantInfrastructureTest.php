<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class TenantInfrastructureProbeJob implements ShouldQueue
{
    public function handle(): void
    {
        // Payload/context is the subject of this test.
    }
}

it('stores database cache entries inside the active tenant database only', function (): void {
    $centralConnection = (string) config('tenancy.database.central_connection');

    Cache::store('database')->put('tenant-isolation-probe', 'tenant-value', 60);

    expect(DB::table('cache')->count())->toBe(1)
        ->and(DB::connection($centralConnection)->table('cache')->count())->toBe(0)
        ->and(Cache::store('database')->get('tenant-isolation-probe'))->toBe('tenant-value');
});

it('stores database queue records centrally while preserving the originating tenant id', function (): void {
    $centralConnection = (string) config('tenancy.database.central_connection');

    expect(Schema::hasTable('jobs'))->toBeFalse()
        ->and(Schema::connection($centralConnection)->hasTable('jobs'))->toBeTrue();

    Queue::connection('database')->push(new TenantInfrastructureProbeJob);

    $job = DB::connection($centralConnection)->table('jobs')->sole();
    $payload = json_decode((string) $job->payload, true, flags: JSON_THROW_ON_ERROR);

    expect(DB::connection($centralConnection)->table('jobs')->count())->toBe(1)
        ->and($payload['tenant_id'] ?? null)->toBe($this->testTenant->getTenantKey());
});
