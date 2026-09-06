<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Throwable;

class CentralSettings
{
    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function passwordMinimumLength(): int
    {
        return max(8, min(64, (int) $this->get('password_min_length', 8)));
    }

    public function passwordRequireMixedCase(): bool
    {
        return $this->boolean('password_require_mixed_case');
    }

    public function passwordRequireNumbers(): bool
    {
        return $this->boolean('password_require_numbers');
    }

    public function passwordRequireSymbols(): bool
    {
        return $this->boolean('password_require_symbols');
    }

    public function passwordRejectCompromised(): bool
    {
        return $this->boolean('password_reject_compromised');
    }

    public function passwordRule(): Password
    {
        $rule = Password::min($this->passwordMinimumLength());

        if ($this->passwordRequireMixedCase()) {
            $rule->mixedCase();
        }
        if ($this->passwordRequireNumbers()) {
            $rule->numbers();
        }
        if ($this->passwordRequireSymbols()) {
            $rule->symbols();
        }
        if ($this->passwordRejectCompromised()) {
            $rule->uncompromised();
        }

        return $rule;
    }

    /** @param array<string, string|int|bool|null> $values */
    public function setMany(array $values): void
    {
        $connection = DB::connection($this->connectionName());
        $now = now();

        foreach ($values as $key => $value) {
            $connection->table('central_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : (string) $value),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $this->cache = null;
    }

    /** @return array<string, string|null> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $this->cache = DB::connection($this->connectionName())->table('central_settings')->pluck('value', 'key')->all();
        } catch (Throwable) {
            $this->cache = [];
        }

        return $this->cache;
    }

    private function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOL);
    }

    private function connectionName(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }
}
