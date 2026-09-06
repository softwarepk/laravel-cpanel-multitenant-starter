<?php

namespace App\Services;

use App\Contracts\CustomDomainProvisioner;
use RuntimeException;

class CpanelApi2CustomDomainProvisioner implements CustomDomainProvisioner
{
    public function __construct(private readonly CpanelClient $cpanel) {}

    public function ensureCustomDomainReady(string $domain): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            throw new RuntimeException('Custom domain cannot be empty.');
        }
        $platformDomain = strtolower(trim((string) config('central.platform.domain')));
        $configuredRoot = trim((string) config('central.platform.document_root'));
        if ($platformDomain === '' || $configuredRoot === '') {
            throw new RuntimeException('The platform domain and document root must be configured.');
        }
        $platformData = $this->domainData($platformDomain);
        if ($platformData === null) {
            throw new RuntimeException('The configured platform domain is not registered in cPanel.');
        }
        [$expectedRoot, $relativeRoot] = $this->resolveDocumentRoots($configuredRoot, (string) ($platformData['homedir'] ?? ''));
        $existing = $this->domainData($domain);
        if ($existing !== null) {
            $this->assertDocumentRoot($domain, $existing, $expectedRoot);

            return;
        }
        $this->cpanel->api2('AddonDomain', 'addaddondomain', ['dir' => $relativeRoot, 'newdomain' => $domain, 'subdomain' => $this->internalSubdomainLabel($domain), 'ftp_is_optional' => 1]);
        $created = null;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $created = $this->domainData($domain);
            if ($created !== null) {
                break;
            }
            usleep(250000);
        }
        if ($created === null) {
            throw new RuntimeException("cPanel reported success, but {$domain} is not visible in DomainInfo yet.");
        }
        $this->assertDocumentRoot($domain, $created, $expectedRoot);
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

    /** @return array{0:string,1:string} */
    private function resolveDocumentRoots(string $configuredRoot, string $home): array
    {
        $home = rtrim($home, '/');
        if ($home === '') {
            throw new RuntimeException('cPanel did not return the account home directory.');
        }
        if (str_starts_with($configuredRoot, '/')) {
            $absolute = rtrim($configuredRoot, '/');
            if (! str_starts_with($absolute.'/', $home.'/')) {
                throw new RuntimeException('The configured document root is outside the cPanel account home directory.');
            }
            $relative = ltrim(substr($absolute, strlen($home)), '/');
        } else {
            $relative = trim($configuredRoot, '/');
            $absolute = $home.'/'.$relative;
        }
        if ($relative === '') {
            throw new RuntimeException('The cPanel addon-domain document root cannot be the account home directory.');
        }

        return [rtrim($absolute, '/'), $relative];
    }

    /** @param array<string,mixed> $data */
    private function assertDocumentRoot(string $domain, array $data, string $expectedRoot): void
    {
        $actual = rtrim((string) ($data['documentroot'] ?? ''), '/');
        if ($actual === '' || $actual !== $expectedRoot) {
            throw new RuntimeException("The cPanel domain {$domain} exists but is mapped to a different document root. Refusing to change it automatically.");
        }
    }

    private function internalSubdomainLabel(string $domain): string
    {
        return 'tenant-'.substr(hash('sha256', $domain), 0, 16);
    }
}
