<?php

namespace App\Services;

use App\Models\CentralAdmin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class WebInstaller
{
    public function __construct(
        private readonly EnvironmentFile $environment,
        private readonly InstallationState $state,
    ) {}

    /** @param array<string, mixed> $data @return array{central_domain:string,central_database:string,tenant_database_user:string} */
    public function install(array $data, string $requestHost): array
    {
        $cpanel = $this->cpanelClient($data);

        $this->verifyHostedDomain($cpanel, $requestHost, (string) $data['document_root']);
        $this->verifyPlatformDomain($cpanel, (string) $data['platform_domain']);
        $this->verifyDatabaseConfigurationWithClient($cpanel, $data);

        // Only record a resumable installation after the submitted cPanel and
        // database configuration has passed the read-only safety checks.
        $this->state->begin([
            'central_database' => (string) $data['central_db_name'],
            'central_db_user' => (string) $data['central_db_user'],
            'tenant_db_user' => (string) $data['tenant_db_user'],
        ]);

        $this->ensureDatabaseUsers($cpanel, $data);

        // Existing cPanel users are never silently given a new password. Prove
        // the submitted credentials work before assigning any new privileges.
        $this->pdo(
            (string) $data['db_host'],
            (int) $data['db_port'],
            (string) $data['central_db_user'],
            (string) $data['central_db_password'],
        );
        $this->pdo(
            (string) $data['tenant_db_host'],
            (int) $data['tenant_db_port'],
            (string) $data['tenant_db_user'],
            (string) $data['tenant_db_password'],
        );

        $this->ensureCentralDatabase($cpanel, $data);

        $centralPdo = $this->pdo(
            (string) $data['db_host'],
            (int) $data['db_port'],
            (string) $data['central_db_user'],
            (string) $data['central_db_password'],
            (string) $data['central_db_name'],
        );

        if (! $this->state->pendingDatabaseMatches((string) $data['central_db_name']) && $this->databaseHasTables($centralPdo)) {
            throw new RuntimeException('The selected central database is not empty. Use an empty database for a new installation.');
        }

        $applicationKey = trim((string) config('app.key'));
        if ($applicationKey === '') {
            $applicationKey = 'base64:'.base64_encode(random_bytes(32));
        }

        $this->environment->write($this->environmentValues($data, $applicationKey));
        $this->configureCurrentProcess($data, $applicationKey);

        DB::purge('mysql');

        $exit = Artisan::call('migrate', [
            '--database' => 'mysql',
            '--force' => true,
        ]);
        if ($exit !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'Central database migration failed.');
        }

        CentralAdmin::query()->updateOrCreate(
            ['email' => strtolower((string) $data['admin_email'])],
            [
                'name' => (string) $data['admin_name'],
                'password' => (string) $data['admin_password'],
            ],
        );

        // Keep this request on non-database stores until the fresh schema exists
        // and the installer has fully completed. The .env already contains the
        // desired production stores for the next request.
        config([
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
        ]);

        Artisan::call('optimize:clear');

        // The .env flag is the authoritative durable lock. The completion marker
        // is secondary, so failure to write that redundant marker cannot turn an
        // otherwise successful installation into an ambiguous failure.
        $this->environment->write(['INSTALLATION_COMPLETE' => true]);
        config(['installer.complete' => true]);
        $this->state->complete([
            'central_domain' => (string) $data['central_domain'],
            'central_database' => (string) $data['central_db_name'],
        ]);

        return [
            'central_domain' => (string) $data['central_domain'],
            'central_database' => (string) $data['central_db_name'],
            'tenant_database_user' => (string) $data['tenant_db_user'],
        ];
    }

    /** @param array<string, mixed> $data @return array{database_host:string} */
    public function verifyCpanelConfiguration(array $data, string $requestHost): array
    {
        $cpanel = $this->cpanelClient($data);
        $this->verifyHostedDomain($cpanel, $requestHost, (string) $data['document_root']);
        $this->verifyPlatformDomain($cpanel, (string) $data['platform_domain']);

        return ['database_host' => $cpanel->databaseHost()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{database_host:string,checks:list<string>}
     */
    public function verifyDatabaseConfiguration(array $data): array
    {
        return $this->verifyDatabaseConfigurationWithClient($this->cpanelClient($data), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{database_host:string,checks:list<string>}
     */
    private function verifyDatabaseConfigurationWithClient(InstallerCpanelClient $cpanel, array $data): array
    {
        $reportedHost = $this->verifyDatabaseHosts($cpanel, $data);
        $centralDatabase = (string) $data['central_db_name'];
        $centralUser = (string) $data['central_db_user'];
        $tenantUser = (string) $data['tenant_db_user'];
        $centralDatabaseExists = $cpanel->databaseExists($centralDatabase);
        $centralUserExists = $cpanel->userExists($centralUser);
        $tenantUserExists = $cpanel->userExists($tenantUser);
        $checks = ["MySQL/MariaDB host [{$reportedHost}] is permitted."];

        if ($centralUserExists) {
            $this->verifyExistingUserCredentials(
                (string) $data['db_host'],
                (int) $data['db_port'],
                $centralUser,
                (string) $data['central_db_password'],
            );
            $checks[] = "Central database user [{$centralUser}] already exists and its password was verified.";
        } else {
            $checks[] = "Central database user [{$centralUser}] does not exist and will be created.";
        }

        if ($tenantUserExists) {
            $this->verifyExistingUserCredentials(
                (string) $data['tenant_db_host'],
                (int) $data['tenant_db_port'],
                $tenantUser,
                (string) $data['tenant_db_password'],
            );
            $checks[] = "Tenant database user [{$tenantUser}] already exists and its password was verified.";
        } else {
            $checks[] = "Tenant database user [{$tenantUser}] does not exist and will be created.";
        }

        if (! $centralDatabaseExists) {
            $checks[] = "Central database [{$centralDatabase}] does not exist and will be created.";

            return [
                'database_host' => $reportedHost,
                'checks' => $checks,
            ];
        }

        if (! $centralUserExists) {
            throw new RuntimeException("Central database [{$centralDatabase}] already exists, but user [{$centralUser}] does not. For a safe first installation, choose a new empty database name or use an existing user that can inspect this database.");
        }

        try {
            $centralPdo = $this->pdo(
                (string) $data['db_host'],
                (int) $data['db_port'],
                $centralUser,
                (string) $data['central_db_password'],
                $centralDatabase,
            );
        } catch (RuntimeException $e) {
            throw new RuntimeException("Central database [{$centralDatabase}] already exists, but it could not be safely inspected with user [{$centralUser}]. Grant that user access first or choose a new empty database name.", $e->getCode(), previous: $e);
        }

        if ($this->databaseHasTables($centralPdo)) {
            if (! $this->state->pendingDatabaseMatches($centralDatabase)) {
                throw new RuntimeException("Central database [{$centralDatabase}] already contains tables. Use an empty database for a new installation.");
            }

            $checks[] = "Central database [{$centralDatabase}] contains tables from this recorded interrupted installation and can be resumed.";
        } else {
            $checks[] = "Central database [{$centralDatabase}] already exists and is empty.";
        }

        return [
            'database_host' => $reportedHost,
            'checks' => $checks,
        ];
    }

    /** @param array<string, mixed> $data */
    private function cpanelClient(array $data): InstallerCpanelClient
    {
        return new InstallerCpanelClient(
            (string) $data['cpanel_host'],
            (int) $data['cpanel_port'],
            (string) $data['cpanel_user'],
            (string) $data['cpanel_token'],
        );
    }

    /** @param array<string, mixed> $data */
    private function ensureDatabaseUsers(InstallerCpanelClient $cpanel, array $data): void
    {
        $centralUser = (string) $data['central_db_user'];
        $tenantUser = (string) $data['tenant_db_user'];

        if (! $cpanel->userExists($centralUser)) {
            $cpanel->createUser($centralUser, (string) $data['central_db_password']);
        }
        if (! $cpanel->userExists($tenantUser)) {
            $cpanel->createUser($tenantUser, (string) $data['tenant_db_password']);
        }
    }

    /** @param array<string, mixed> $data */
    private function ensureCentralDatabase(InstallerCpanelClient $cpanel, array $data): void
    {
        $centralDatabase = (string) $data['central_db_name'];
        $centralUser = (string) $data['central_db_user'];

        if (! $cpanel->databaseExists($centralDatabase)) {
            $cpanel->createDatabase($centralDatabase);
        }

        $cpanel->grantAll($centralUser, $centralDatabase);
    }

    private function verifyHostedDomain(InstallerCpanelClient $cpanel, string $requestHost, string $documentRoot): void
    {
        $expected = $this->normalizedPath(public_path());
        $submitted = $this->normalizedPath($documentRoot);
        if ($expected === '' || $submitted !== $expected) {
            throw new RuntimeException('The installer document root must be this deployment\'s Laravel public directory.');
        }

        $data = $cpanel->domainData($requestHost);
        if (strtolower((string) ($data['domain'] ?? '')) !== strtolower($requestHost)) {
            throw new RuntimeException('The cPanel API credentials do not manage the hostname currently serving the installer.');
        }

        $actual = $this->normalizedPath((string) ($data['documentroot'] ?? ''));
        if ($actual === '' || $actual !== $expected) {
            throw new RuntimeException("The cPanel document root [{$actual}] does not match the Laravel public directory [{$expected}].");
        }
    }

    private function verifyPlatformDomain(InstallerCpanelClient $cpanel, string $platformDomain): void
    {
        $data = $cpanel->domainData($platformDomain);
        if (strtolower((string) ($data['domain'] ?? '')) !== strtolower($platformDomain)) {
            throw new RuntimeException("The tenant platform root [{$platformDomain}] is not managed by this cPanel account.");
        }
    }

    /** @param array<string, mixed> $data */
    private function verifyDatabaseHosts(InstallerCpanelClient $cpanel, array $data): string
    {
        $reported = strtolower($cpanel->databaseHost());
        $safeLocalHosts = ['localhost', '127.0.0.1', '::1'];

        foreach (['db_host', 'tenant_db_host'] as $key) {
            $submitted = strtolower(trim((string) $data[$key]));
            if (! in_array($submitted, $safeLocalHosts, true) && $submitted !== $reported) {
                throw new RuntimeException("Database host [{$submitted}] is not localhost or the MySQL/MariaDB host reported by cPanel [{$reported}].");
            }
        }

        return $reported;
    }

    private function verifyExistingUserCredentials(string $host, int $port, string $user, string $password): void
    {
        try {
            $this->pdo($host, $port, $user, $password);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Database user [{$user}] already exists, but the supplied password does not authenticate at [{$host}:{$port}]. Enter that user's existing password or choose a new database username.", $e->getCode(), previous: $e);
        }
    }

    private function normalizedPath(string $path): string
    {
        $path = rtrim(trim($path), '/\\');
        if ($path === '') {
            return '';
        }

        $real = realpath($path);

        return rtrim(str_replace('\\', '/', $real !== false ? $real : $path), '/');
    }

    private function pdo(string $host, int $port, string $user, string $password, ?string $database = null): PDO
    {
        $dsn = 'mysql:host='.$host.';port='.$port.';charset=utf8mb4';
        if ($database !== null && $database !== '') {
            $dsn .= ';dbname='.$database;
        }

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->query('SELECT 1');

            return $pdo;
        } catch (\PDOException $e) {
            throw new RuntimeException("Database login failed for [{$user}] at [{$host}:{$port}].", $e->getCode(), previous: $e);
        }
    }

    private function databaseHasTables(PDO $pdo): bool
    {
        $statement = $pdo->query('SHOW TABLES');

        return $statement !== false && $statement->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $data @return array<string, string|int|bool|null> */
    private function environmentValues(array $data, string $applicationKey): array
    {
        return [
            'APP_NAME' => (string) $data['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $applicationKey,
            'APP_DEBUG' => false,
            'APP_URL' => (string) $data['app_url'],
            'FORCE_HTTPS' => true,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $data['db_host'],
            'DB_PORT' => (int) $data['db_port'],
            'DB_DATABASE' => (string) $data['central_db_name'],
            'DB_USERNAME' => (string) $data['central_db_user'],
            'DB_PASSWORD' => (string) $data['central_db_password'],
            'TENANT_DB_DRIVER' => 'mysql',
            'TENANT_DB_HOST' => (string) $data['tenant_db_host'],
            'TENANT_DB_PORT' => (int) $data['tenant_db_port'],
            'TENANT_DB_USERNAME' => (string) $data['tenant_db_user'],
            'TENANT_DB_PASSWORD' => (string) $data['tenant_db_password'],
            'SESSION_DRIVER' => 'database',
            'SESSION_SECURE_COOKIE' => true,
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'sync',
            'CENTRAL_DOMAINS' => (string) $data['central_domain'],
            'TENANT_PLATFORM_DOMAIN' => (string) $data['platform_domain'],
            'TENANT_PLATFORM_DOCUMENT_ROOT' => (string) $data['document_root'],
            'CUSTOM_DOMAIN_DNS_TARGET' => (string) $data['custom_domain_dns_target'],
            'CPANEL_TENANT_DB_PREFIX' => (string) $data['tenant_db_prefix'],
            'CPANEL_API_HOST' => (string) $data['cpanel_host'],
            'CPANEL_API_PORT' => (int) $data['cpanel_port'],
            'CPANEL_API_USER' => (string) $data['cpanel_user'],
            'CPANEL_API_TOKEN' => (string) $data['cpanel_token'],
            'FORTIFY_REGISTRATION' => (bool) $data['registration'],
            'FORTIFY_EMAIL_VERIFICATION' => (bool) $data['verification'],
            'MAIL_MAILER' => 'log',
            'INSTALLATION_COMPLETE' => false,
        ];
    }

    /** @param array<string, mixed> $data */
    private function configureCurrentProcess(array $data, string $applicationKey): void
    {
        $mysql = config('database.connections.mysql', []);
        $mysql['host'] = (string) $data['db_host'];
        $mysql['port'] = (string) $data['db_port'];
        $mysql['database'] = (string) $data['central_db_name'];
        $mysql['username'] = (string) $data['central_db_user'];
        $mysql['password'] = (string) $data['central_db_password'];

        config([
            'app.key' => $applicationKey,
            'app.url' => (string) $data['app_url'],
            'database.default' => 'mysql',
            'database.connections.mysql' => $mysql,
            'tenancy.database.central_connection' => 'mysql',
            'tenancy.central_domains' => [(string) $data['central_domain']],
            'central.platform.domain' => (string) $data['platform_domain'],
            'central.platform.document_root' => (string) $data['document_root'],
            'central.custom_domain.dns_target' => (string) $data['custom_domain_dns_target'],
            'central.cpanel.tenant_database_prefix' => (string) $data['tenant_db_prefix'],
            'central.cpanel.api_host' => (string) $data['cpanel_host'],
            'central.cpanel.api_port' => (int) $data['cpanel_port'],
            'central.cpanel.api_user' => (string) $data['cpanel_user'],
            'central.cpanel.api_token' => (string) $data['cpanel_token'],
        ]);
    }
}
