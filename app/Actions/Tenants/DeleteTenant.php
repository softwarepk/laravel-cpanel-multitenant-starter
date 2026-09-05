<?php

namespace App\Actions\Tenants;

use App\Contracts\PlatformDomainDeprovisioner;
use App\Contracts\TenantDatabaseProvisioner;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DeleteTenant
{
    public function __construct(private readonly TenantDatabaseProvisioner $databases, private readonly PlatformDomainDeprovisioner $platformDomains, private readonly CentralAuditLogger $audit) {}

    public function handle(Tenant $tenant): void
    {
        if ($tenant->status !== 'suspended') throw new RuntimeException('A tenant must be suspended before it can be permanently deleted.');
        $tenantId = (string) $tenant->getTenantKey();
        $database = (string) ($tenant->database_name ?: $tenant->getInternal('tenancy_db_name'));
        $domains = $tenant->domains()->get(['domain', 'type']);
        $platform = $domains->firstWhere('type', 'platform')?->domain;
        $custom = $domains->where('type', 'custom')->pluck('domain')->values()->all();
        $storagePath = $this->tenantStoragePath($tenantId);

        $this->audit->log('tenant.deletion_started', 'Permanent tenant deletion started.', tenantId: $tenantId, context: ['database' => $database, 'domains' => $domains->pluck('domain')->all()]);
        if (is_string($platform) && $platform !== '') $this->platformDomains->deletePlatformDomain($platform);
        if (File::isDirectory($storagePath) && ! File::deleteDirectory($storagePath)) throw new RuntimeException('Tenant storage could not be deleted. No database or control-plane records were removed.');
        if ($database !== '') $this->databases->deleteDatabase($database);
        $tenant->delete();
        $this->audit->log('tenant.deleted', 'Tenant permanently deleted.', tenantId: $tenantId, context: ['database' => $database, 'tenant_storage_deleted' => true, 'custom_domains_retained_in_cpanel' => $custom]);
    }

    private function tenantStoragePath(string $tenantId): string
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $tenantId)) throw new RuntimeException('Tenant ID is not safe for storage cleanup.');
        $root = rtrim(base_path('storage'), DIRECTORY_SEPARATOR);
        $path = $root.DIRECTORY_SEPARATOR.'tenant'.$tenantId;
        if (! str_starts_with($path, $root.DIRECTORY_SEPARATOR.'tenant')) throw new RuntimeException('Tenant storage path is outside the application storage directory.');
        return $path;
    }
}
