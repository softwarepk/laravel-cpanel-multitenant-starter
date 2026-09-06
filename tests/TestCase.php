<?php

namespace Tests;

use App\Http\Middleware\InitializeTenantFromHost;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected ?Tenant $testTenant = null;

    protected string $testTenantDomain = 'tenant.localhost';

    protected static ?string $sharedTenantDatabaseName = null;

    protected static bool $sharedTenantDatabaseMigrated = false;

    protected static bool $sharedTenantDatabaseCleanupRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestTenant();
        URL::forceRootUrl('http://'.$this->testTenantDomain);

        // Most feature tests exercise application behavior inside an already
        // initialized tenant transaction. Dedicated foundation tests opt back
        // into the real host resolver.
        $this->withoutMiddleware(InitializeTenantFromHost::class);
    }

    protected function tearDown(): void
    {
        URL::forceRootUrl(null);

        if (tenancy()->initialized) {
            $connection = DB::connection();

            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            tenancy()->end();
        }

        $this->testTenant = null;

        parent::tearDown();
    }

    /**
     * Keep ordinary feature requests on the tenant hostname.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, string>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        $server = array_merge([
            'HTTP_HOST' => $this->testTenantDomain,
            'SERVER_NAME' => $this->testTenantDomain,
        ], $server);

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Exercise the real global hostname resolver for tenancy-boundary tests.
     *
     * @param  array<string, mixed>  $server
     * @param  array<string, mixed>  $parameters
     */
    public function callThroughTenantResolver(string $method, string $uri, array $server = [], array $parameters = []): TestResponse
    {
        $tenant = $this->testTenant;
        $server = array_merge([
            'HTTP_HOST' => $this->testTenantDomain,
            'SERVER_NAME' => $this->testTenantDomain,
        ], $server);
        $requestHost = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? $this->testTenantDomain);

        if (tenancy()->initialized) {
            $connection = DB::connection();

            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            tenancy()->end();
        }

        $this->withMiddleware(InitializeTenantFromHost::class);
        URL::forceRootUrl('http://'.$requestHost);

        try {
            return parent::call($method, $uri, parameters: $parameters, server: $server);
        } finally {
            URL::forceRootUrl('http://'.$this->testTenantDomain);
            $this->withoutMiddleware(InitializeTenantFromHost::class);

            if ($tenant instanceof Tenant && ! tenancy()->initialized) {
                tenancy()->initialize($tenant);
            }

            if (tenancy()->initialized && DB::connection()->transactionLevel() === 0) {
                DB::connection()->beginTransaction();
            }
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function setUpTestTenant(): void
    {
        $databaseName = static::sharedTenantDatabaseName();
        $databasePath = database_path($databaseName);

        config([
            'database.connections.tenant_template' => [
                'driver' => 'sqlite',
                'url' => null,
                'database' => $databasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
                'busy_timeout' => null,
                'journal_mode' => null,
                'synchronous' => null,
                'transaction_mode' => 'DEFERRED',
            ],
        ]);

        if (! static::$sharedTenantDatabaseMigrated) {
            if (is_file($databasePath)) {
                @unlink($databasePath);
            }

            if (! touch($databasePath)) {
                throw new \RuntimeException("Unable to create tenant test database [{$databasePath}].");
            }

            $this->testTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::create([
                'id' => 'test-tenant',
                'name' => 'Test Tenant',
                'status' => 'active',
                'provisioning_status' => 'active',
                'database_name' => $databaseName,
            ]));
            $this->testTenant->setInternal('db_name', $databaseName);
            $this->testTenant->save();

            $this->testTenant->run(function (): void {
                $exit = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);

                if ($exit !== 0) {
                    throw new \RuntimeException(trim(Artisan::output()) ?: 'Tenant test migration failed.');
                }
            });

            static::$sharedTenantDatabaseMigrated = true;
            static::registerSharedTenantDatabaseCleanup();
        } else {
            $this->testTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::create([
                'id' => 'test-tenant',
                'name' => 'Test Tenant',
                'status' => 'active',
                'provisioning_status' => 'active',
                'database_name' => $databaseName,
            ]));
            $this->testTenant->setInternal('db_name', $databaseName);
            $this->testTenant->save();
        }

        $this->testTenant->domains()->create([
            'domain' => $this->testTenantDomain,
            'type' => 'platform',
            'status' => 'active',
            'is_primary' => true,
        ]);

        tenancy()->initialize($this->testTenant);
        DB::connection()->beginTransaction();
    }

    protected static function sharedTenantDatabaseName(): string
    {
        return static::$sharedTenantDatabaseName ??= 'test-tenant-shared.sqlite';
    }

    protected static function registerSharedTenantDatabaseCleanup(): void
    {
        if (static::$sharedTenantDatabaseCleanupRegistered) {
            return;
        }

        static::$sharedTenantDatabaseCleanupRegistered = true;
        $databaseName = static::sharedTenantDatabaseName();

        register_shutdown_function(static function () use ($databaseName): void {
            $path = database_path($databaseName);

            if (is_file($path)) {
                @unlink($path);
            }
        });
    }
}
