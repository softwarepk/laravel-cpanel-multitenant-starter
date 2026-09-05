<?php

namespace App\Contracts;

interface TenantDatabaseProvisioner
{
    public function ensureDatabaseReady(string $databaseName): void;

    public function deleteDatabase(string $databaseName): void;
}
