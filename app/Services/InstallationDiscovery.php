<?php

namespace App\Services;

use Illuminate\Http\Request;

class InstallationDiscovery
{
    public function __construct(private readonly EnvironmentFile $environment) {}

    /** @return array{defaults: array<string, string|int|bool>, checks: list<array{label:string,ok:bool,detail:string}>} */
    public function discover(Request $request): array
    {
        $existing = $this->environment->existingNonSecretValues();
        $host = strtolower($request->getHost());
        $accountUser = $this->detectAccountUser();
        $serverHost = $this->detectServerHost($host);
        $documentRoot = public_path();
        $httpsUrl = 'https://'.$host;

        $defaults = [
            'app_name' => $this->existingOrDefault($existing, 'APP_NAME', 'Laravel cPanel Multi-Tenant Starter'),
            'app_url' => $this->existingOrDefault($existing, 'APP_URL', $httpsUrl, ['http://localhost']),
            'central_domain' => $this->existingOrDefault($existing, 'CENTRAL_DOMAINS', $host, ['127.0.0.1,localhost', 'localhost']),
            'platform_domain' => $this->existingOrDefault($existing, 'TENANT_PLATFORM_DOMAIN', $host, ['tenants.example.com']),
            'document_root' => $this->existingOrDefault($existing, 'TENANT_PLATFORM_DOCUMENT_ROOT', $documentRoot),
            'custom_domain_dns_target' => $this->existingOrDefault($existing, 'CUSTOM_DOMAIN_DNS_TARGET', $host),
            'cpanel_host' => $this->existingOrDefault($existing, 'CPANEL_API_HOST', $serverHost),
            'cpanel_port' => $this->existingIntOrDefault($existing, 'CPANEL_API_PORT', 2083),
            'cpanel_user' => $this->existingOrDefault($existing, 'CPANEL_API_USER', $accountUser),
            'db_host' => $this->existingOrDefault($existing, 'DB_HOST', 'localhost'),
            'db_port' => $this->existingIntOrDefault($existing, 'DB_PORT', 3306),
            'central_db_name' => $this->existingOrDefault($existing, 'DB_DATABASE', $this->prefixedName($accountUser, 'mtcentral'), ['database/database.sqlite']),
            'central_db_user' => $this->existingOrDefault($existing, 'DB_USERNAME', $this->prefixedName($accountUser, 'mtctl')),
            'tenant_db_host' => $this->existingOrDefault($existing, 'TENANT_DB_HOST', 'localhost'),
            'tenant_db_port' => $this->existingIntOrDefault($existing, 'TENANT_DB_PORT', 3306),
            'tenant_db_user' => $this->existingOrDefault($existing, 'TENANT_DB_USERNAME', $this->prefixedName($accountUser, 'mtapp')),
            'tenant_db_prefix' => $this->existingOrDefault($existing, 'CPANEL_TENANT_DB_PREFIX', $this->prefixedName($accountUser, 'mtt_')),
            'registration' => false,
            'verification' => true,
        ];

        $serverDocumentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
        $resolvedServerRoot = $serverDocumentRoot !== '' ? realpath($serverDocumentRoot) : false;
        $resolvedPublicRoot = realpath($documentRoot);

        $checks = [
            $this->check('PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION),
            $this->extensionCheck('PDO MySQL', 'pdo_mysql'),
            $this->extensionCheck('OpenSSL', 'openssl'),
            $this->extensionCheck('Mbstring', 'mbstring'),
            $this->extensionCheck('Tokenizer', 'tokenizer'),
            $this->extensionCheck('Fileinfo', 'fileinfo'),
            $this->check('Storage is writable', is_writable(storage_path()), storage_path()),
            $this->check('Bootstrap cache is writable', is_writable(base_path('bootstrap/cache')), base_path('bootstrap/cache')),
            $this->check('.env can be created or updated', $this->environmentWritable(), base_path('.env')),
            $this->check(
                'Domain document root points to Laravel public',
                $resolvedServerRoot !== false && $resolvedPublicRoot !== false && $resolvedServerRoot === $resolvedPublicRoot,
                $serverDocumentRoot !== '' ? $serverDocumentRoot : 'Not reported by web server',
            ),
            $this->check('Current request uses HTTPS', $request->isSecure(), $request->getScheme().'://'.$host),
        ];

        return ['defaults' => $defaults, 'checks' => $checks];
    }

    private function detectAccountUser(): string
    {
        if (preg_match('#^/home/([^/]+)/#', str_replace('\\', '/', base_path()), $matches) === 1) {
            return $matches[1];
        }

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info)) {
                return $info['name'];
            }
        }

        $environmentUser = getenv('USER');
        if (is_string($environmentUser) && trim($environmentUser) !== '') {
            return trim($environmentUser);
        }

        return get_current_user();
    }

    private function detectServerHost(string $fallback): string
    {
        $hostname = gethostname();

        return is_string($hostname) && str_contains($hostname, '.') ? strtolower($hostname) : $fallback;
    }

    private function prefixedName(string $accountUser, string $suffix): string
    {
        $accountUser = preg_replace('/[^A-Za-z0-9_]/', '', $accountUser) ?: '';

        return $accountUser !== '' ? $accountUser.'_'.$suffix : $suffix;
    }

    /**
     * Fresh deployments commonly start by copying .env.example. Treat blank
     * values and known development placeholders as absent so the installer can
     * still discover deployment-specific defaults.
     *
     * @param  array<string, string>  $existing
     * @param  list<string>  $placeholders
     */
    private function existingOrDefault(array $existing, string $key, string $default, array $placeholders = []): string
    {
        $value = trim($existing[$key] ?? '');

        if ($value === '' || in_array($value, $placeholders, true)) {
            return $default;
        }

        return $value;
    }

    /** @param array<string, string> $existing */
    private function existingIntOrDefault(array $existing, string $key, int $default): int
    {
        $value = trim($existing[$key] ?? '');

        return $value !== '' && ctype_digit($value) ? (int) $value : $default;
    }

    /** @return array{label:string,ok:bool,detail:string} */
    private function extensionCheck(string $label, string $extension): array
    {
        return $this->check($label.' extension', extension_loaded($extension), extension_loaded($extension) ? 'Loaded' : 'Missing');
    }

    /** @return array{label:string,ok:bool,detail:string} */
    private function check(string $label, bool $ok, string $detail): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }

    private function environmentWritable(): bool
    {
        $path = base_path('.env');

        return is_file($path) ? is_writable($path) : is_writable(base_path());
    }
}
