<?php

use App\Services\EnvironmentFile;
use App\Services\InstallationDiscovery;
use Illuminate\Http\Request;
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
        ->assertSee('Step 1 of 6')
        ->assertSee('Test cPanel connection')
        ->assertSee('Verify database configuration')
        ->assertSee('Review setup')
        ->assertSee(public_path())
        ->assertSee('Install &amp; configure', false);
});

it('uses discovered deployment defaults instead of blank or development template placeholders', function (): void {
    $environment = new class extends EnvironmentFile
    {
        /** @return array<string, string> */
        public function existingNonSecretValues(): array
        {
            return [
                'APP_NAME' => 'Multi-Tenant Starter',
                'APP_URL' => 'http://localhost',
                'CENTRAL_DOMAINS' => '127.0.0.1,localhost',
                'TENANT_PLATFORM_DOMAIN' => 'tenants.example.com',
                'TENANT_PLATFORM_DOCUMENT_ROOT' => '',
                'CUSTOM_DOMAIN_DNS_TARGET' => '',
                'DB_DATABASE' => 'database/database.sqlite',
                'DB_USERNAME' => '',
                'TENANT_DB_USERNAME' => '',
                'CPANEL_TENANT_DB_PREFIX' => '',
                'CPANEL_API_HOST' => '',
                'CPANEL_API_USER' => '',
            ];
        }
    };

    $request = Request::create('https://central.test/install');
    $defaults = (new InstallationDiscovery($environment))->discover($request)['defaults'];

    expect($defaults)
        ->toMatchArray([
            'app_url' => 'https://central.test',
            'central_domain' => 'central.test',
            'platform_domain' => 'central.test',
            'document_root' => public_path(),
            'custom_domain_dns_target' => 'central.test',
        ])
        ->and($defaults['app_url'])->not->toBe('http://localhost')
        ->and($defaults['central_domain'])->not->toBe('127.0.0.1,localhost')
        ->and($defaults['platform_domain'])->not->toBe('tenants.example.com')
        ->and($defaults['central_db_name'])->not->toBe('database/database.sqlite');
});

it('preflights cPanel ownership before the database step', function (): void {
    Http::fakeSequence()
        ->push(['result' => ['status' => 1, 'data' => ['domain' => 'central.test', 'documentroot' => public_path()]]])
        ->push(['result' => ['status' => 1, 'data' => ['domain' => 'central.test', 'documentroot' => public_path()]]])
        ->push(['result' => ['status' => 1, 'data' => ['mysql_host' => 'localhost']]]);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->withServerVariables(['HTTPS' => 'on', 'DOCUMENT_ROOT' => public_path()])
        ->post('https://central.test/install/cpanel-check', [
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

it('preflights new database resources without creating them', function (): void {
    Http::fakeSequence()
        ->push(['result' => ['status' => 1, 'data' => ['mysql_host' => 'localhost']]])
        ->push(['result' => ['status' => 1, 'data' => []]])
        ->push(['result' => ['status' => 1, 'data' => []]])
        ->push(['result' => ['status' => 1, 'data' => []]]);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('https://central.test/install/database-check', [
            'cpanel_host' => '1.1.1.1',
            'cpanel_port' => 2083,
            'cpanel_user' => 'tester',
            'cpanel_token' => str_repeat('a', 32),
            'db_host' => 'localhost',
            'db_port' => 3306,
            'central_db_name' => 'tester_central',
            'central_db_user' => 'tester_ctl',
            'central_db_password' => str_repeat('b', 16),
            'tenant_db_host' => 'localhost',
            'tenant_db_port' => 3306,
            'tenant_db_user' => 'tester_app',
            'tenant_db_password' => str_repeat('c', 16),
            'tenant_db_prefix' => 'tester_t_',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'database_host' => 'localhost',
        ])
        ->assertJsonFragment(['Central database [tester_central] does not exist and will be created.'])
        ->assertJsonFragment(['Central database user [tester_ctl] does not exist and will be created.'])
        ->assertJsonFragment(['Tenant database user [tester_app] does not exist and will be created.']);

    Http::assertSentCount(4);
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
