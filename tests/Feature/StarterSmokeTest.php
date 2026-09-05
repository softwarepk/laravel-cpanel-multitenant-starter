<?php

use Illuminate\Support\Facades\Artisan;

it('registers the starter installation and central admin commands', function (): void {
    expect(Artisan::all())
        ->toHaveKeys(['starter:install', 'central:admin']);
});
