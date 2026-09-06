<?php

namespace App\Actions\Tenants;

use App\Contracts\TenantDatabaseProvisioner;
use App\Contracts\TenantDomainProvisioner;
use App\Enums\UserRole;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantDeletionRecord;
use App\Models\User;
use App\Services\CentralAuditLogger;
use App\Services\PlatformHttpsVerifier;
use App\Services\TenantDatabaseNamer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ProvisionTenant
{
    public function __construct(
        private readonly TenantDatabaseProvisioner $databases,
        private readonly TenantDomainProvisioner $domains,
        private readonly CentralAuditLogger $audit,
        private readonly PlatformHttpsVerifier $https,
        private readonly TenantDatabaseNamer $databaseNames,
    ) {}

    public function handle(string $tenantId, string $name, string $adminName, string $adminEmail, string $adminPassword): Tenant
    {
        $tenantId = strtolower(trim($tenantId));
        validator(['password' => $adminPassword], ['password' => ['required', 'string', Password::default()]])->validate();

        $platformDomain = $this->platformDomainFor($tenantId);
        $rootDomain = $this->platformRootDomain();
        $documentRoot = $this->platformDocumentRoot();

        if (Domain::query()->where('domain', $platformDomain)->where('tenant_id', '!=', $tenantId)->exists()) {
            throw new InvalidArgumentException("Platform domain [{$platformDomain}] is already assigned to another tenant.");
        }

        $tenant = Tenant::query()->find($tenantId);
        $databaseName = $this->databaseNameFor($tenant, $tenantId);

        if (! $tenant instanceof Tenant && TenantDeletionRecord::query()
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)->orWhere('database_name', $databaseName))
            ->exists()) {
            throw new InvalidArgumentException('This tenant identity was used previously and is retained in deletion history. Choose a new tenant ID rather than reusing deleted tenant infrastructure.');
        }

        if (Tenant::query()->where('database_name', $databaseName)->whereKeyNot($tenantId)->exists()) {
            throw new RuntimeException("Tenant database [{$databaseName}] is already assigned to another tenant.");
        }

        if (! $tenant instanceof Tenant) {
            $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::create([
                'id' => $tenantId,
                'name' => $name,
                'status' => 'provisioning',
                'provisioning_status' => 'pending',
                'database_name' => $databaseName,
                'initial_admin_email' => $adminEmail,
            ]));
            $tenant->setInternal('db_name', $databaseName);
            $tenant->save();
        } else {
            $tenant->update([
                'name' => $name,
                'status' => 'provisioning',
                'provisioning_status' => 'pending',
                'initial_admin_email' => $adminEmail,
                'provisioning_error' => null,
            ]);
        }

        $platform = $tenant->domains()->where('type', 'platform')->first();

        if ($platform instanceof Domain && $platform->domain !== $platformDomain) {
            throw new InvalidArgumentException("Tenant already has platform domain [{$platform->domain}], expected [{$platformDomain}].");
        }

        $platform ??= $tenant->domains()->create([
            'domain' => $platformDomain,
            'type' => 'platform',
            'status' => 'pending',
            'is_primary' => true,
        ]);

        try {
            $tenant->update(['provisioning_status' => 'domain']);
            $platform->update(['status' => 'pending', 'ssl_verified_at' => null]);
            $this->domains->ensurePlatformDomainReady($platformDomain, $rootDomain, $documentRoot);
            $platform->update(['cpanel_verified_at' => now(), 'is_primary' => true]);
            $this->audit->log('tenant.platform_domain_ready', 'Tenant platform domain created or confirmed.', tenantId: $tenantId, context: ['domain' => $platformDomain]);

            $tenant->update(['provisioning_status' => 'database']);
            $this->databases->ensureDatabaseReady($databaseName);
            $this->audit->log('tenant.database_ready', 'Tenant database created or confirmed.', tenantId: $tenantId, context: ['database' => $databaseName]);

            $tenant->update(['provisioning_status' => 'migrating']);
            $tenant->run(function (): void {
                $exit = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);

                if ($exit !== 0) {
                    throw new RuntimeException(trim(Artisan::output()) ?: 'Tenant migration failed.');
                }
            });

            $tenant->update(['provisioning_status' => 'administrator']);
            $tenant->run(function () use ($adminName, $adminEmail, $adminPassword): void {
                $admin = User::query()->firstOrNew(['email' => $adminEmail]);
                $admin->name = $adminName;
                $admin->password = $adminPassword;
                $admin->role = UserRole::Administrator->value;
                $admin->email_verified_at ??= Carbon::now();
                $admin->save();
            });

            $tenant->update([
                'status' => 'provisioning',
                'provisioning_status' => 'https_pending',
                'provisioning_error' => null,
                'suspended_at' => null,
            ]);

            if ($this->https->isReady($platformDomain)) {
                $this->activateAfterHttps($tenant, $platform);
            } else {
                $this->audit->log('tenant.platform_https_pending', 'Tenant application is ready and waiting for a trusted HTTPS certificate.', tenantId: $tenantId, context: ['domain' => $platformDomain]);
            }

            return $tenant->refresh();
        } catch (Throwable $e) {
            $tenant->update([
                'status' => 'failed',
                'provisioning_status' => 'failed',
                'provisioning_error' => Str::limit($e->getMessage(), 4000),
            ]);

            throw $e;
        }
    }

    public function activateAfterHttps(Tenant $tenant, Domain $platform): Tenant
    {
        $platform->update(['status' => 'active', 'ssl_verified_at' => now(), 'is_primary' => true]);
        $tenant->update([
            'status' => 'active',
            'provisioning_status' => 'active',
            'provisioning_error' => null,
            'provisioned_at' => now(),
            'suspended_at' => null,
        ]);
        $this->audit->log('tenant.platform_https_ready', 'Trusted HTTPS certificate confirmed; tenant activated.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $platform->domain]);

        return $tenant->refresh();
    }

    public function platformDomainFor(string $tenantId): string
    {
        $root = $this->platformRootDomain();
        $tenantId = strtolower(trim($tenantId));

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $tenantId)) {
            throw new InvalidArgumentException('Tenant ID must be a valid DNS label using lowercase letters, numbers, and hyphens.');
        }

        return $tenantId.'.'.$root;
    }

    private function platformRootDomain(): string
    {
        $domain = strtolower(trim((string) config('central.platform.domain')));

        if ($domain === '' || ! preg_match('/^(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domain)) {
            throw new InvalidArgumentException('TENANT_PLATFORM_DOMAIN is not configured with a valid hostname.');
        }

        return $domain;
    }

    private function platformDocumentRoot(): string
    {
        $root = trim((string) config('central.platform.document_root'));

        if ($root === '') {
            throw new InvalidArgumentException('TENANT_PLATFORM_DOCUMENT_ROOT is not configured.');
        }

        return $root;
    }

    private function databaseNameFor(?Tenant $tenant, string $tenantId): string
    {
        if (! $tenant instanceof Tenant) {
            return $this->databaseNames->forTenant($tenantId);
        }

        $columnName = trim((string) $tenant->database_name);
        $internalName = trim((string) $tenant->getInternal('db_name'));

        if ($columnName !== '' && $internalName !== '' && $columnName !== $internalName) {
            throw new RuntimeException("Tenant database identity is inconsistent: [{$columnName}] does not match [{$internalName}].");
        }

        $databaseName = $columnName !== '' ? $columnName : $internalName;

        if ($databaseName === '') {
            $databaseName = $this->databaseNames->forTenant($tenantId);
            $tenant->database_name = $databaseName;
            $tenant->setInternal('db_name', $databaseName);
            $tenant->save();

            return $databaseName;
        }

        if ($columnName === '') {
            $tenant->database_name = $databaseName;
        }

        if ($internalName === '') {
            $tenant->setInternal('db_name', $databaseName);
        }

        if ($tenant->isDirty()) {
            $tenant->save();
        }

        return $databaseName;
    }
}
