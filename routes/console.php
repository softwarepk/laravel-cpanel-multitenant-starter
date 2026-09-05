<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment('Build the smallest useful thing, then make it dependable.');
})->purpose('Display a short project reminder');
