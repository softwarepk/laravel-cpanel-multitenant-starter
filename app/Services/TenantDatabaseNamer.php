<?php

namespace App\Services;

use InvalidArgumentException;

class TenantDatabaseNamer
{
    public function forTenant(string $tenantId): string
    {
        $tenantId = strtolower(trim($tenantId));
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $tenantId)) {
            throw new InvalidArgumentException('Tenant ID must be a valid DNS label using lowercase letters, numbers, and hyphens.');
        }

        $prefix = (string) config('central.cpanel.tenant_database_prefix', '');
        if ($prefix !== '' && ! preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            throw new InvalidArgumentException('CPANEL_TENANT_DB_PREFIX may contain letters, numbers, and underscores only.');
        }

        $hash = substr(hash('sha256', $tenantId), 0, 10);
        $base = str_replace('-', '_', $tenantId);
        $available = 64 - strlen($prefix) - strlen($hash) - 1;
        if ($available < 1) {
            throw new InvalidArgumentException('CPANEL_TENANT_DB_PREFIX is too long to generate a safe tenant database name.');
        }

        $base = rtrim(substr($base, 0, $available), '_');
        if ($base === '') {
            throw new InvalidArgumentException('Generated tenant database name is invalid.');
        }

        $name = $prefix.$base.'_'.$hash;
        if (strlen($name) > 64 || ! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Generated tenant database name is invalid.');
        }

        return $name;
    }
}
