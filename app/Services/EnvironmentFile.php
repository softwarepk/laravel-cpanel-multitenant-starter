<?php

namespace App\Services;

use RuntimeException;

class EnvironmentFile
{
    /** @param array<string, string|int|bool|null> $values */
    public function write(array $values): void
    {
        $path = base_path('.env');
        $contents = is_file($path) ? file_get_contents($path) : file_get_contents(base_path('.env.example'));

        if ($contents === false) {
            throw new RuntimeException('Unable to read the environment template.');
        }

        foreach ($values as $key => $value) {
            $contents = $this->setValue($contents, $key, $this->encodeValue($value));
        }

        $temporary = $path.'.installer.'.bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the temporary environment file.');
        }

        @chmod($temporary, 0600);

        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to replace the environment file atomically.');
        }

        @chmod($path, 0600);
    }

    public function hasConfiguredApplicationKey(): bool
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            return false;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (! preg_match('/^APP_KEY=(.*)$/', trim($line), $matches)) {
                continue;
            }

            return trim($this->decodeValue($matches[1])) !== '';
        }

        return false;
    }

    /** @return array<string, string> */
    public function existingNonSecretValues(): array
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $allowed = [
            'APP_NAME',
            'APP_URL',
            'CENTRAL_DOMAINS',
            'TENANT_PLATFORM_DOMAIN',
            'TENANT_PLATFORM_DOCUMENT_ROOT',
            'CUSTOM_DOMAIN_DNS_TARGET',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'TENANT_DB_HOST',
            'TENANT_DB_PORT',
            'TENANT_DB_USERNAME',
            'CPANEL_TENANT_DB_PREFIX',
            'CPANEL_API_HOST',
            'CPANEL_API_PORT',
            'CPANEL_API_USER',
        ];

        $values = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (! preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $matches)) {
                continue;
            }
            if (! in_array($matches[1], $allowed, true)) {
                continue;
            }

            $values[$matches[1]] = $this->decodeValue($matches[2]);
        }

        return $values;
    }

    private function setValue(string $contents, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*/m';
        $replacement = $key.'='.$value;

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace($pattern, $replacement, $contents, 1);
        }

        return rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
    }

    private function encodeValue(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode an environment value.');
        }

        return $encoded;
    }

    private function decodeValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (($value[0] ?? '') === '"') {
            $decoded = json_decode($value, true);

            return is_string($decoded) ? $decoded : trim($value, '"');
        }

        return trim($value, "'\"");
    }
}
