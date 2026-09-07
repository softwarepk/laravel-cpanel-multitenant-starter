<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstallerCpanelClient
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $token,
    ) {
        if ($this->port !== 2083) {
            throw new RuntimeException('The first-run installer only permits the secure cPanel API port 2083.');
        }

        $this->assertPublicApiHost();
    }

    /** @param array<string, string|int|bool> $arguments @return array<string, mixed> */
    public function uapi(string $module, string $function, array $arguments = []): array
    {
        $url = sprintf(
            'https://%s:%d/execute/%s/%s',
            $this->host,
            $this->port,
            rawurlencode($module),
            rawurlencode($function),
        );

        try {
            $response = Http::acceptJson()
                ->withHeaders(['Authorization' => 'cpanel '.$this->user.':'.$this->token])
                ->connectTimeout(5)
                ->timeout(30)
                ->get($url, $arguments);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Unable to connect to the cPanel API host.', $e->getCode(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('cPanel UAPI request failed with HTTP '.$response->status().'.');
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new RuntimeException('cPanel UAPI returned an invalid response.');
        }

        $wrapped = $decoded['result'] ?? null;
        $result = is_array($wrapped) ? $wrapped : $decoded;

        if (! array_key_exists('status', $result)) {
            throw new RuntimeException('cPanel UAPI returned an invalid result.');
        }

        $errors = $this->normalizeErrors($result['errors'] ?? null);
        if ((int) ($result['status'] ?? 0) !== 1 || $errors !== []) {
            throw new RuntimeException($errors !== [] ? implode(' ', $errors) : 'cPanel UAPI call failed.');
        }

        return $result;
    }

    public function databaseHost(): string
    {
        $result = $this->uapi('Variables', 'get_server_information', ['name' => 'mysql_host']);
        $data = $result['data'] ?? null;
        $host = is_array($data) ? ($data['mysql_host'] ?? null) : null;

        if (! is_string($host) || trim($host) === '') {
            throw new RuntimeException('cPanel did not report the MySQL/MariaDB host for this account.');
        }

        return strtolower(trim($host));
    }

    public function databaseExists(string $database): bool
    {
        $result = $this->uapi('Mysql', 'list_databases');

        return $this->containsExactString($result['data'] ?? [], $database);
    }

    public function userExists(string $user): bool
    {
        $result = $this->uapi('Mysql', 'list_users');

        return $this->containsExactString($result['data'] ?? [], $user);
    }

    public function createDatabase(string $database): void
    {
        $this->uapi('Mysql', 'create_database', ['name' => $database]);
    }

    public function createUser(string $user, string $password): void
    {
        $this->uapi('Mysql', 'create_user', ['name' => $user, 'password' => $password]);
    }

    public function grantAll(string $user, string $database): void
    {
        $this->uapi('Mysql', 'set_privileges_on_database', [
            'user' => $user,
            'database' => $database,
            'privileges' => 'ALL PRIVILEGES',
        ]);
    }

    /** @return array<string, mixed> */
    public function domainData(string $domain): array
    {
        $result = $this->uapi('DomainInfo', 'single_domain_data', ['domain' => $domain]);
        $data = $result['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException("cPanel did not return domain data for [{$domain}].");
        }

        return $data;
    }

    private function assertPublicApiHost(): void
    {
        $host = trim($this->host);
        if ($host === '' || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
            throw new RuntimeException('The cPanel API host is invalid.');
        }

        $addresses = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } elseif (function_exists('dns_get_record')) {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip']) && is_string($record['ip'])) {
                        $addresses[] = $record['ip'];
                    }
                    if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        if ($addresses === []) {
            $ipv4 = gethostbynamel($host);
            if (is_array($ipv4)) {
                $addresses = $ipv4;
            }
        }

        if ($addresses === []) {
            throw new RuntimeException('The cPanel API host could not be resolved.');
        }

        foreach (array_unique($addresses) as $address) {
            $public = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
            if ($public === false) {
                throw new RuntimeException('The first-run installer only connects to a publicly routable cPanel API address.');
            }
        }
    }

    /** @return list<string> */
    private function normalizeErrors(mixed $errors): array
    {
        if (is_string($errors)) {
            $errors = [$errors];
        }
        if (! is_array($errors)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $error): string => trim(is_scalar($error) ? (string) $error : ''),
            $errors,
        )));
    }

    private function containsExactString(mixed $value, string $needle): bool
    {
        if (is_string($value)) {
            return $value === $needle;
        }
        if (! is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if ($this->containsExactString($item, $needle)) {
                return true;
            }
        }

        return false;
    }
}
