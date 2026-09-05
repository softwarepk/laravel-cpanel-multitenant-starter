<?php

namespace App\Services;

class CpanelProvisioningReadiness
{
    /** @return list<string> */
    public function missingRequirements(): array
    {
        $missing = [];
        $driver = (string) config('database.connections.tenant_template.driver');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $missing[] = 'TENANT_DB_DRIVER must be mysql or mariadb';
        }

        $required = [
            'TENANT_PLATFORM_DOMAIN' => config('central.platform.domain'),
            'TENANT_PLATFORM_DOCUMENT_ROOT' => config('central.platform.document_root'),
            'CPANEL_DB_USER' => config('central.cpanel.database_user'),
            'CPANEL_API_HOST' => config('central.cpanel.api_host'),
            'CPANEL_API_USER' => config('central.cpanel.api_user'),
            'CPANEL_API_TOKEN' => config('central.cpanel.api_token'),
            'TENANT_DB_USERNAME' => config('database.connections.tenant_template.username'),
            'TENANT_DB_PASSWORD' => config('database.connections.tenant_template.password'),
        ];

        foreach ($required as $key => $value) {
            if (trim((string) $value) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public function isReady(): bool
    {
        return $this->missingRequirements() === [];
    }
}
