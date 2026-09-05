<?php

namespace App\Services;

use App\Contracts\PlatformDomainDeprovisioner;
use RuntimeException;

class CpanelApi2PlatformDomainDeprovisioner implements PlatformDomainDeprovisioner
{
    public function __construct(private readonly CpanelClient $cpanel) {}

    public function deletePlatformDomain(string $domain): void
    {
        $domain = strtolower(trim($domain));
        $rootDomain = strtolower(trim((string) config('central.platform.domain')));
        $configuredRoot = trim((string) config('central.platform.document_root'));

        if ($domain === '' || $rootDomain === '' || $configuredRoot === '') {
            throw new RuntimeException('The platform domain and document root must be configured before domain cleanup.');
        }

        $suffix = '.'.$rootDomain;
        if (! str_ends_with($domain, $suffix)) {
            throw new RuntimeException("Refusing to delete [{$domain}] because it is outside the configured platform domain [{$rootDomain}].");
        }

        $label = substr($domain, 0, -strlen($suffix));
        if ($label === '' || str_contains($label, '.') || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
            throw new RuntimeException("Refusing to delete platform domain [{$domain}] because its tenant label is invalid.");
        }

        if (app()->runningUnitTests() && trim((string) config('central.cpanel.api_host')) === '') return;

        $data = $this->domainData($domain);
        if ($data === null) return;
        if (($data['domain'] ?? null) !== $domain) throw new RuntimeException("cPanel returned unexpected domain data while preparing to delete [{$domain}].");

        $actual = rtrim((string) ($data['documentroot'] ?? ''), '/');
        $home = rtrim((string) ($data['homedir'] ?? ''), '/');
        $expected = str_starts_with($configuredRoot, '/') ? rtrim($configuredRoot, '/') : $home.'/'.trim($configuredRoot, '/');
        if ($home === '' || $actual === '' || $actual !== $expected) {
            throw new RuntimeException("Refusing to delete platform domain [{$domain}] because its cPanel document root does not match the configured application document root.");
        }

        $this->cpanel->api2('SubDomain', 'delsubdomain', ['domain' => $domain]);
        for ($attempt = 0; $attempt < 4; $attempt++) { if ($this->domainData($domain) === null) return; usleep(250000); }
        throw new RuntimeException("cPanel reported success, but platform domain [{$domain}] is still present.");
    }

    /** @return array<string,mixed>|null */
    private function domainData(string $domain): ?array
    {
        try { $result = $this->cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $domain]); }
        catch (RuntimeException $e) { if (str_contains(strtolower($e->getMessage()), 'unable to locate the domain')) return null; throw $e; }
        $data = $result['data'] ?? null;
        return is_array($data) ? $data : null;
    }
}
