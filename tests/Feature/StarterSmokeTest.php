<?php

use Illuminate\Support\Facades\Artisan;

it('registers the starter installation and administration commands', function (): void {
    expect(Artisan::all())
        ->toHaveKeys(['starter:install', 'central:admin', 'tenant:local-create']);
});
