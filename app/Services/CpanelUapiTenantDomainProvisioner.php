<?php

namespace App\Services;

use App\Contracts\TenantDomainProvisioner;
use RuntimeException;

class CpanelUapiTenantDomainProvisioner implements TenantDomainProvisioner
{
    public function __construct(private readonly CpanelClient $cpanel) {}

    public function ensurePlatformDomainReady(string $domain, string $rootDomain, string $documentRoot): void
    {
        $suffix = '.'.$rootDomain;
        if (! str_ends_with($domain, $suffix)) {
            throw new RuntimeException("Platform domain [{$domain}] is not beneath [{$rootDomain}].");
        }

        $label = substr($domain, 0, -strlen($suffix));
        if ($label === '' || str_contains($label, '.') || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
            throw new RuntimeException("Platform domain label [{$label}] is invalid.");
        }

        if (! $this->domainExists($domain)) {
            $this->cpanel->uapi('SubDomain', 'addsubdomain', ['domain' => $label, 'rootdomain' => $rootDomain, 'dir' => $documentRoot, 'disallowdot' => '1']);
        }

        $result = $this->cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $domain]);
        $data = $result['data'] ?? [];
        if (! is_array($data) || ($data['domain'] ?? null) !== $domain) {
            throw new RuntimeException("cPanel did not return the expected domain [{$domain}].");
        }

        $actual = rtrim((string) ($data['documentroot'] ?? ''), '/');
        $home = rtrim((string) ($data['homedir'] ?? ''), '/');
        $configured = trim($documentRoot);
        $expected = str_starts_with($configured, '/') ? rtrim($configured, '/') : $home.'/'.trim($configured, '/');
        if ($actual === '' || $actual !== $expected) {
            throw new RuntimeException("Domain [{$domain}] points to [{$actual}] instead of [{$expected}].");
        }
    }

    private function domainExists(string $domain): bool
    {
        $result = $this->cpanel->uapi('DomainInfo', 'list_domains');
        return $this->containsExactString($result['data'] ?? [], $domain);
    }

    private function containsExactString(mixed $value, string $needle): bool
    {
        if (is_string($value)) return $value === $needle;
        if (! is_array($value)) return false;
        foreach ($value as $item) if ($this->containsExactString($item, $needle)) return true;
        return false;
    }
}
