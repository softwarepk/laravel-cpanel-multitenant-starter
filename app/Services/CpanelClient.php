<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CpanelClient
{
    /** @param array<string, string|int|bool> $arguments @return array<string, mixed> */
    public function uapi(string $module, string $function, array $arguments = []): array
    {
        $response = $this->request()->get($this->baseUrl().'/execute/'.rawurlencode($module).'/'.rawurlencode($function), $arguments);
        if (! $response->successful()) {
            throw new RuntimeException('cPanel UAPI request failed with HTTP '.$response->status().'.');
        }
        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new RuntimeException('cPanel UAPI returned an invalid response.');
        }
        $wrapped = $decoded['result'] ?? null;
        $result = is_array($wrapped) ? $wrapped : ((array_key_exists('status', $decoded) && (array_key_exists('data', $decoded) || array_key_exists('errors', $decoded) || array_key_exists('messages', $decoded))) ? $decoded : null);
        if (! is_array($result)) {
            throw new RuntimeException('cPanel UAPI returned an invalid result.');
        }
        $errors = $this->normalizeErrors($result['errors'] ?? null);
        if ((int) ($result['status'] ?? 0) !== 1 || $errors !== []) {
            throw new RuntimeException($errors !== [] ? implode(' ', $errors) : 'cPanel UAPI call failed.');
        }

        return $result;
    }

    /** @param array<string, string|int|bool> $arguments @return array<string, mixed> */
    public function api2(string $module, string $function, array $arguments = []): array
    {
        $query = array_merge(['cpanel_jsonapi_apiversion' => 2, 'cpanel_jsonapi_module' => $module, 'cpanel_jsonapi_func' => $function], $arguments);
        $response = $this->request()->get($this->baseUrl().'/json-api/cpanel', $query);
        if (! $response->successful()) {
            throw new RuntimeException('cPanel API 2 request failed with HTTP '.$response->status().'.');
        }
        $decoded = $response->json();
        $result = is_array($decoded) ? ($decoded['cpanelresult'] ?? null) : null;
        if (! is_array($result)) {
            throw new RuntimeException('cPanel API 2 returned an invalid response.');
        }
        $errors = [];
        if ((int) data_get($result, 'event.result', 0) !== 1) {
            $errors[] = trim((string) ($result['error'] ?? 'cPanel API 2 call failed.'));
        }
        foreach (is_array($result['data'] ?? null) ? $result['data'] : [] as $item) {
            if (is_array($item) && array_key_exists('result', $item) && (int) $item['result'] === 0) {
                $errors[] = trim((string) ($item['reason'] ?? 'cPanel API 2 call failed.'));
            }
        }
        $errors = array_values(array_filter(array_unique($errors)));
        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }

        return $result;
    }

    private function request(): PendingRequest
    {
        $host = trim((string) config('central.cpanel.api_host'));
        $user = trim((string) config('central.cpanel.api_user'));
        $token = trim((string) config('central.cpanel.api_token'));
        $connectTimeout = max(1, (int) config('central.cpanel.api_connect_timeout', 5));
        $timeout = max($connectTimeout, (int) config('central.cpanel.api_timeout', 90));
        if ($host === '' || $user === '' || $token === '') {
            throw new RuntimeException('cPanel API host, user and token must be configured.');
        }

        return Http::acceptJson()->withHeaders(['Authorization' => 'cpanel '.$user.':'.$token])->connectTimeout($connectTimeout)->timeout($timeout);
    }

    private function baseUrl(): string
    {
        $host = trim((string) config('central.cpanel.api_host'));
        $port = (int) config('central.cpanel.api_port', 2083);
        if ($host === '') {
            throw new RuntimeException('cPanel API host, user and token must be configured.');
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('cPanel API port is invalid.');
        }

        return sprintf('https://%s:%d', $host, $port);
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

        return array_values(array_filter(array_map(static fn (mixed $error): string => trim(is_scalar($error) ? (string) $error : ''), $errors)));
    }
}
