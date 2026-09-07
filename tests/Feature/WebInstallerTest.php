<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $directory = storage_path('framework/testing/web-installer');
    File::ensureDirectoryExists($directory);
    File::delete([$directory.'/pending', $directory.'/complete']);

    config([
        'app.env' => 'production',
        'installer.complete' => false,
        'installer.legacy_configured' => false,
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

it('serves the installer as a guided wizard without normal web session middleware', function (): void {
    $response = $this
        ->withHeader('Host', 'central.test')
        ->withServerVariables(['HTTPS' => 'on', 'DOCUMENT_ROOT' => public_path()])
        ->get('/install');

    $response
        ->assertOk()
        ->assertSee('First-run deployment')
        ->assertSee('Step 1 of 7')
        ->assertSee('Test cPanel connection')
        ->assertSee('Background processing')
        ->assertSee('Review setup')
        ->assertSee(public_path())
        ->assertSee('Install &amp; configure', false);
});

it('preflights cPanel ownership before the database step', function (): void {
    Http::fakeSequence()
        ->push(['result' => ['status' => 1, 'data' => ['domain' => 'central.test', 'documentroot' => public_path()]]])
        ->push(['result' => ['status' => 1, 'data' => ['domain' => 'central.test', 'documentroot' => public_path()]]])
        ->push(['result' => ['status' => 1, 'data' => ['mysql_host' => 'localhost']]]);

    $response = $this
        ->withHeader('Host', 'central.test')
        ->withHeader('Accept', 'application/json')
        ->withServerVariables(['HTTPS' => 'on', 'DOCUMENT_ROOT' => public_path()])
        ->post('/install/cpanel-check', [
            'central_domain' => 'central.test',
            'platform_domain' => 'central.test',
            'document_root' => public_path(),
            'cpanel_host' => '1.1.1.1',
            'cpanel_port' => 2083,
            'cpanel_user' => 'tester',
            'cpanel_token' => str_repeat('a', 32),
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'database_host' => 'localhost',
        ]);
});

it('treats an existing production app key as an already configured legacy deployment', function (): void {
    config(['installer.legacy_configured' => true]);

    $response = $this
        ->withHeader('Host', 'central.test')
        ->withServerVariables(['HTTPS' => 'on'])
        ->get('/install');

    $response->assertRedirect('/central/login');
});

it('keeps a pending installation recoverable even after an app key has been written', function (): void {
    config(['installer.legacy_configured' => true]);
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
