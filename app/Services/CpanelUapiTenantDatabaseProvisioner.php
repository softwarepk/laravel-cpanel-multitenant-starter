<?php

namespace App\Services;

use App\Contracts\TenantDatabaseProvisioner;
use RuntimeException;

class CpanelUapiTenantDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function __construct(private readonly CpanelClient $cpanel) {}

    public function ensureDatabaseReady(string $databaseName): void
    {
        $user = (string) config('central.cpanel.database_user');
        if ($user === '') {
            throw new RuntimeException('CPANEL_DB_USER is not configured.');
        }

        if (! $this->databaseExists($databaseName)) {
            $this->cpanel->uapi('Mysql', 'create_database', ['name' => $databaseName]);
        }

        $this->cpanel->uapi('Mysql', 'set_privileges_on_database', ['user' => $user, 'database' => $databaseName, 'privileges' => 'ALL']);
    }

    public function deleteDatabase(string $databaseName): void
    {
        if ($this->databaseExists($databaseName)) {
            $this->cpanel->uapi('Mysql', 'delete_database', ['name' => $databaseName]);
        }
    }

    private function databaseExists(string $databaseName): bool
    {
        $result = $this->cpanel->uapi('Mysql', 'list_databases');

        return $this->containsExactString($result['data'] ?? [], $databaseName);
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
