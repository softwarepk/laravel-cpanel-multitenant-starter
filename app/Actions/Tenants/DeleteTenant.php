<?php

namespace App\Actions\Tenants;

use App\Contracts\CustomDomainDeprovisioner;
use App\Contracts\PlatformDomainDeprovisioner;
use App\Contracts\TenantDatabaseProvisioner;
use App\Models\CentralAdmin;
use App\Models\Tenant;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class DeleteTenant
{
    public function __construct(
        private readonly TenantDatabaseProvisioner $databases,
        private readonly PlatformDomainDeprovisioner $platformDomains,
        private readonly CustomDomainDeprovisioner $customDomains,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function handle(Tenant $tenant, ?CentralAdmin $admin = null): TenantDeletionRecord
    {
        if ($tenant->status !== 'suspended') {
            throw new RuntimeException('A tenant must be suspended before it can be permanently deleted.');
        }

        $tenantId = (string) $tenant->getTenantKey();
        $database = (string) ($tenant->database_name ?: $tenant->getInternal('db_name'));
        $domains = $tenant->domains()->get(['domain', 'type']);
        $platform = $domains->firstWhere('type', 'platform')?->domain;
        $custom = $domains->where('type', 'custom')->pluck('domain')->values()->all();
        $storagePath = $this->tenantStoragePath($tenantId);

        $history = TenantDeletionRecord::query()->create([
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant->name,
            'database_name' => $database !== '' ? $database : null,
            'platform_domain' => is_string($platform) && $platform !== '' ? $platform : null,
            'custom_domains' => $custom,
            'central_admin_id' => $admin?->getKey(),
            'status' => 'started',
            'cleanup_results' => [],
        ]);

        $this->audit->log('tenant.deletion_started', 'Permanent tenant deletion started.', tenantId: $tenantId, context: [
            'database' => $database,
            'domains' => $domains->pluck('domain')->all(),
            'deletion_record_id' => $history->getKey(),
        ], admin: $admin);

        $results = [];

        if (is_string($platform) && $platform !== '') {
            $results['platform_domain'] = $this->attemptCleanup($platform, function () use ($platform): void {
                $this->platformDomains->deletePlatformDomain($platform);
            });
        } else {
            $results['platform_domain'] = ['status' => 'not_applicable', 'target' => null];
        }

        foreach ($custom as $domain) {
            $results['custom_domain:'.$domain] = $this->attemptCleanup($domain, function () use ($domain): void {
                $this->customDomains->deleteCustomDomain($domain);
            });
        }

        if (File::isDirectory($storagePath)) {
            $results['storage'] = $this->attemptCleanup($storagePath, function () use ($storagePath): void {
                if (! File::deleteDirectory($storagePath)) {
                    throw new RuntimeException('Tenant storage could not be deleted.');
                }
            });
        } else {
            $results['storage'] = ['status' => 'not_present', 'target' => $storagePath];
        }

        if ($database !== '') {
            $results['database'] = $this->attemptCleanup($database, function () use ($database): void {
                $this->databases->deleteDatabase($database);
            });
        } else {
            $results['database'] = ['status' => 'not_applicable', 'target' => null];
        }

        try {
            $tenant->delete();
            $results['central_record'] = ['status' => 'deleted', 'target' => $tenantId];
        } catch (Throwable $e) {
            report($e);
            $results['central_record'] = ['status' => 'failed', 'target' => $tenantId, 'error' => $e->getMessage()];
            $history->update([
                'status' => 'failed',
                'cleanup_results' => $results,
                'completed_at' => now(),
            ]);
            throw $e;
        }

        $hasWarnings = collect($results)->contains(fn (mixed $result): bool => is_array($result) && ($result['status'] ?? null) === 'failed');
        $history->update([
            'status' => $hasWarnings ? 'completed_with_warnings' : 'completed',
            'cleanup_results' => $results,
            'completed_at' => now(),
        ]);

        $this->audit->log('tenant.deleted', $hasWarnings ? 'Tenant permanently deleted with infrastructure cleanup warnings.' : 'Tenant permanently deleted.', tenantId: $tenantId, context: [
            'database' => $database,
            'cleanup_results' => $results,
            'deletion_record_id' => $history->getKey(),
        ], admin: $admin);

        return $history->refresh();
    }

    /** @return array{status:string,target:string,error?:string} */
    private function attemptCleanup(string $target, callable $cleanup): array
    {
        try {
            $cleanup();

            return ['status' => 'deleted', 'target' => $target];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'failed', 'target' => $target, 'error' => $e->getMessage()];
        }
    }

    private function tenantStoragePath(string $tenantId): string
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $tenantId)) {
            throw new RuntimeException('Tenant ID is not safe for storage cleanup.');
        }
        $root = rtrim(base_path('storage'), DIRECTORY_SEPARATOR);
        $path = $root.DIRECTORY_SEPARATOR.'tenant'.$tenantId;
        if (! str_starts_with($path, $root.DIRECTORY_SEPARATOR.'tenant')) {
            throw new RuntimeException('Tenant storage path is outside the application storage directory.');
        }

        return $path;
    }
}
