<?php

namespace App\Services;

use App\Contracts\CustomDomainDeprovisioner;
use RuntimeException;

class CpanelApi2CustomDomainDeprovisioner implements CustomDomainDeprovisioner
{
    public function __construct(private readonly CpanelClient $cpanel) {}

    public function deleteCustomDomain(string $domain): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            throw new RuntimeException('Custom domain cannot be empty.');
        }

        $existing = $this->domainData($domain);
        if ($existing === null) {
            return;
        }

        $this->assertApplicationDocumentRoot($domain, $existing);
        $addon = $this->addonDomainData($domain);
        if ($addon === null) {
            throw new RuntimeException("The cPanel domain {$domain} exists but is not listed as an addon domain. Refusing to remove it automatically.");
        }

        // Despite the API parameter being named "subdomain", cPanel expects the
        // addon's domain key (for example username_example.com), not the plain
        // `subdomain` field returned by listaddondomains.
        $domainKey = trim((string) ($addon['domainkey'] ?? ''));
        if ($domainKey === '') {
            throw new RuntimeException("cPanel did not return the domain key required to remove addon domain {$domain}.");
        }

        $this->cpanel->api2('AddonDomain', 'deladdondomain', [
            'domain' => $domain,
            'subdomain' => $domainKey,
        ]);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            if ($this->domainData($domain) === null) {
                return;
            }
            usleep(250000);
        }

        throw new RuntimeException("cPanel reported success, but custom domain [{$domain}] is still present.");
    }

    /** @return array<string, mixed>|null */
    private function addonDomainData(string $domain): ?array
    {
        $result = $this->cpanel->api2('AddonDomain', 'listaddondomains');
        foreach (is_array($result['data'] ?? null) ? $result['data'] : [] as $item) {
            if (is_array($item) && strtolower((string) ($item['domain'] ?? '')) === $domain) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function domainData(string $domain): ?array
    {
        try {
            $result = $this->cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $domain]);
        } catch (RuntimeException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unable to locate the domain')) {
                return null;
            }
            throw $e;
        }

        $data = $result['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    private function assertApplicationDocumentRoot(string $domain, array $data): void
    {
        $configuredRoot = trim((string) config('central.platform.document_root'));
        $actual = rtrim((string) ($data['documentroot'] ?? ''), '/');
        $home = rtrim((string) ($data['homedir'] ?? ''), '/');

        if ($configuredRoot === '' || $home === '') {
            throw new RuntimeException('The platform document root is not configured or cPanel did not return the account home directory.');
        }

        $expected = str_starts_with($configuredRoot, '/')
            ? rtrim($configuredRoot, '/')
            : $home.'/'.trim($configuredRoot, '/');

        if ($actual === '' || $actual !== $expected) {
            throw new RuntimeException("Refusing to remove custom domain [{$domain}] because its cPanel document root does not match the configured application document root.");
        }
    }
}
