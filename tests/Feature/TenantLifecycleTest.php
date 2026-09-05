<?php

use Illuminate\Support\Facades\DB;

it('blocks suspended tenants at the hostname boundary', function (): void {
    $tenant = $this->testTenant;

    $connection = DB::connection();
    while ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
    tenancy()->end();

    $tenant->update([
        'status' => 'suspended',
        'suspended_at' => now(),
    ]);

    try {
        $this->callThroughTenantResolver('GET', '/')->assertNotFound();
    } finally {
        $tenant->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);

        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
            DB::connection()->beginTransaction();
        }
    }
});

it('does not expose central control-center routes on tenant hostnames', function (): void {
    $this->get('/central')->assertNotFound();
});
