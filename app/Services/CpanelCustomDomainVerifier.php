<?php

namespace App\Services;

use App\Contracts\CustomDomainVerifier;
use Throwable;

class CpanelCustomDomainVerifier implements CustomDomainVerifier
{
    public function __construct(private readonly CpanelClient $cpanel) {}

    public function verify(string $domain): array
    {
        $errors = [];
        $dns = $this->dnsPointsToPlatform($domain);
        if (! $dns) {
            $errors[] = 'DNS does not yet resolve to the platform target.';
        }
        $cpanel = $this->cpanelDomainReady($domain);
        if (! $cpanel) {
            $errors[] = 'The custom domain is not registered in cPanel with the application document root.';
        }
        $ssl = $dns && $cpanel && $this->sslReady($domain);
        if ($dns && $cpanel && ! $ssl) {
            $errors[] = 'HTTPS certificate validation is not ready for this domain.';
        }

        return ['dns' => $dns, 'cpanel' => $cpanel, 'ssl' => $ssl, 'errors' => $errors];
    }

    private function dnsPointsToPlatform(string $domain): bool
    {
        $target = strtolower(trim((string) config('central.custom_domain.dns_target')));
        if ($target === '') {
            $target = strtolower(trim((string) config('central.platform.domain')));
        }
        if ($target === '') {
            return false;
        }
        $domainIps = $this->resolveIpv4($domain);
        $targetIps = $this->resolveIpv4($target);

        return $domainIps !== [] && $targetIps !== [] && array_intersect($domainIps, $targetIps) !== [];
    }

    /** @return list<string> */
    private function resolveIpv4(string $hostname): array
    {
        $resolved = gethostbynamel($hostname);

        return is_array($resolved) ? array_values(array_unique(array_filter($resolved, is_string(...)))) : [];
    }

    private function cpanelDomainReady(string $domain): bool
    {
        try {
            $result = $this->cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $domain]);
        } catch (Throwable) {
            return false;
        }
        $data = $result['data'] ?? [];
        if (! is_array($data) || strtolower((string) ($data['domain'] ?? '')) !== $domain) {
            return false;
        }
        $actual = rtrim((string) ($data['documentroot'] ?? ''), '/');
        $home = rtrim((string) ($data['homedir'] ?? ''), '/');
        $configured = trim((string) config('central.platform.document_root'));
        if ($configured === '') {
            return false;
        }
        $expected = str_starts_with($configured, '/') ? rtrim($configured, '/') : $home.'/'.trim($configured, '/');

        return $actual !== '' && $actual === $expected;
    }

    private function sslReady(string $domain): bool
    {
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $domain, 'SNI_enabled' => true]]);
        $socket = @stream_socket_client('ssl://'.$domain.':443', $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);
        if (! is_resource($socket)) {
            return false;
        }
        fclose($socket);

        return true;
    }
}
