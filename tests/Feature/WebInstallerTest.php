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
        ->withHeader('Host', 'central.test')
        ->withServerVariables(['HTTPS' => 'on'])
        ->get('/');

    $response->assertRedirect('/install');
});

it('serves the installer without the normal web session middleware', function (): void {
    $response = $this
        ->withHeader('Host', 'central.test')
        ->withServerVariables(['HTTPS' => 'on', 'DOCUMENT_ROOT' => public_path()])
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
        ->withHeader('Host', 'central.test')
        ->withServerVariables(['HTTPS' => 'on'])
        ->get('/install');

    $response->assertRedirect('/central/login');
});

it('keeps a pending installation recoverable even after an app key has been written', function (): void {
    config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
    File::put(config('installer.pending_file'), 'pending');

    $response = $this
        ->withHeader('Host', 'central.test')
        ->withServerVariables(['HTTPS' => 'on', 'DOCUMENT_ROOT' => public_path()])
        ->get('/install');

    $response->assertOk()->assertSee('First-run deployment');
});

it('locks the installer when the completion marker exists', function (): void {
    File::put(config('installer.complete_file'), 'complete');

    $response = $this
        ->withHeader('Host', 'central.test')
        ->withServerVariables(['HTTPS' => 'on'])
        ->get('/install');

    $response->assertRedirect('/central/login');
});
