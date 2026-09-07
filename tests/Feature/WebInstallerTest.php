<?php

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $directory = storage_path('framework/testing/web-installer');
    File::ensureDirectoryExists($directory);
    File::delete([$directory.'/pending', $directory.'/complete']);

    config([
        'app.env' => 'production',
        'app.key' => null,
        'installer.complete' => false,
        'installer.pending_file' => $directory.'/pending',
        'installer.complete_file' => $directory.'/complete',
        'tenancy.central_domains' => ['central.test'],
    ]);
});

afterEach(function (): void {
    File::delete([
        storage_path('framework/testing/web-installer/pending'),
        storage_path('framework/testing/web-installer/complete'),
    ]);
});

it('redirects an uninstalled production deployment to the web installer', function (): void {
    $response = $this
        ->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'central.test'])
        ->get('/');

    $response->assertRedirect('/install');
});

it('serves the installer without the normal web session middleware', function (): void {
    $response = $this
        ->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'central.test', 'DOCUMENT_ROOT' => public_path()])
        ->get('/install');

    $response
        ->assertOk()
        ->assertSee('First-run deployment')
        ->assertSee(public_path())
        ->assertSee('Install &amp; configure', false);
});

it('treats an existing production app key as an already configured legacy deployment', function (): void {
    config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);

    $response = $this
        ->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'central.test'])
        ->get('/install');

    $response->assertRedirect('/central/login');
});
