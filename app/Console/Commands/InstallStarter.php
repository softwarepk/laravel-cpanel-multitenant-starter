<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class InstallStarter extends Command
{
    protected $signature = 'starter:install
        {--name= : Application name}
        {--central-domain= : Central control-plane hostname}
        {--platform-domain= : Root hostname for permanent tenant subdomains}
        {--document-root= : Shared cPanel document root for tenant domains}
        {--registration= : Enable public tenant-user registration (yes/no)}
        {--verification= : Require tenant-user email verification (yes/no)}';

    protected $description = 'Configure the multi-tenant starter for a new application';

    public function handle(): int
    {
        $name = $this->stringOption('name', 'Application name', (string) config('app.name', 'Multi-Tenant Application'));
        $centralDomain = $this->stringOption('central-domain', 'Central control-plane hostname', $this->firstCentralDomain());
        $platformDomain = $this->stringOption('platform-domain', 'Tenant platform root domain', (string) config('central.platform.domain', 'tenants.example.com'));
        $documentRoot = $this->stringOption('document-root', 'cPanel document root shared by tenant domains', (string) config('central.platform.document_root', ''));
        $registration = $this->resolveBooleanOption('registration', 'Enable public tenant-user registration?', false);
        $verification = $this->resolveBooleanOption('verification', 'Require tenant-user email verification?', true);

        $this->setEnvironmentValue('APP_NAME', $this->quoteEnvironmentValue($name));
        $this->setEnvironmentValue('CENTRAL_DOMAINS', $centralDomain);
        $this->setEnvironmentValue('TENANT_PLATFORM_DOMAIN', $platformDomain);
        if ($documentRoot !== '') {
            $this->setEnvironmentValue('TENANT_PLATFORM_DOCUMENT_ROOT', $documentRoot);
        }
        $this->setEnvironmentValue('FORTIFY_REGISTRATION', $registration ? 'true' : 'false');
        $this->setEnvironmentValue('FORTIFY_EMAIL_VERIFICATION', $verification ? 'true' : 'false');

        $this->callSilent('config:clear');

        $this->newLine();
        $this->components->info('Multi-tenant starter configuration updated.');
        $this->line('Application: '.$name);
        $this->line('Control plane: '.$centralDomain);
        $this->line('Tenant platform domain: '.$platformDomain);
        $this->line('Public tenant registration: '.($registration ? 'enabled' : 'disabled'));
        $this->line('Email verification: '.($verification ? 'required' : 'not required'));
        $this->newLine();
        $this->components->warn('Configure the cPanel API credentials and tenant database credentials before provisioning tenants.');

        return self::SUCCESS;
    }

    private function stringOption(string $option, string $question, string $default): string
    {
        $value = $this->option($option);
        if (is_string($value) && trim($value) !== '') return trim($value);
        if (! $this->input->isInteractive()) return trim($default);
        return trim((string) $this->ask($question, $default));
    }

    private function resolveBooleanOption(string $option, string $question, bool $default): bool
    {
        $value = $this->option($option);
        if (is_string($value) && $value !== '') {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'y', 'on' => true,
                '0', 'false', 'no', 'n', 'off' => false,
                default => throw new InvalidArgumentException("Invalid --{$option} value [{$value}]. Use yes or no."),
            };
        }
        return $this->input->isInteractive() ? $this->confirm($question, $default) : $default;
    }

    private function firstCentralDomain(): string
    {
        $domains = config('tenancy.central_domains', ['localhost']);
        return is_array($domains) && isset($domains[0]) ? (string) $domains[0] : 'localhost';
    }

    private function setEnvironmentValue(string $key, string $value): void
    {
        $path = base_path('.env');
        if (! file_exists($path)) copy(base_path('.env.example'), $path);
        $contents = file_get_contents($path);
        if ($contents === false) throw new RuntimeException('Unable to read the .env file.');
        $pattern = '/^'.preg_quote($key, '/').'=.*/m';
        $replacement = $key.'='.$value;
        $contents = preg_match($pattern, $contents) === 1
            ? (string) preg_replace($pattern, $replacement, $contents, 1)
            : rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
        if (file_put_contents($path, $contents) === false) throw new RuntimeException('Unable to update the .env file.');
    }

    private function quoteEnvironmentValue(string $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) throw new RuntimeException('Unable to encode the environment value.');
        return $encoded;
    }
}
