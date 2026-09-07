<?php

namespace App\Jobs;

use App\Actions\Tenants\ProvisionTenant;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

class ProvisionTenantJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $operationId,
        public readonly string $adminName,
        public readonly string $adminEmail,
        private readonly string $encryptedAdminPassword,
    ) {}

    public function handle(ProvisionTenant $provision, CentralAuditLogger $audit): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);

        if ((string) $tenant->getInternal('provisioning_operation_id') !== $this->operationId) {
            return;
        }

        $tenant = $provision->handle(
            $this->tenantId,
            (string) ($tenant->name ?: $tenant->getTenantKey()),
            $this->adminName,
            $this->adminEmail,
            Crypt::decryptString($this->encryptedAdminPassword),
        );

        $domain = $tenant->domains()->where('type', 'platform')->value('domain');
        $active = $tenant->isActive();
        $tenant->setInternal('provisioning_operation_id', null);
        $tenant->save();

        $audit->log(
            'tenant.provisioned',
            $active ? 'Tenant provisioned successfully.' : 'Tenant application provisioned and awaiting trusted HTTPS.',
            tenantId: (string) $tenant->getTenantKey(),
            context: [
                'name' => $tenant->name,
                'domain' => $domain,
                'database' => $tenant->database_name,
                'https_pending' => ! $active,
            ],
        );
    }

    public function failed(?Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant instanceof Tenant
            && (string) $tenant->getInternal('provisioning_operation_id') === $this->operationId
            && $tenant->provisioning_status !== 'active') {
            $tenant->update([
                'status' => 'failed',
                'provisioning_status' => 'failed',
                'provisioning_error' => Str::limit($exception?->getMessage() ?: 'Background provisioning stopped unexpectedly.', 4000),
            ]);
            $tenant->setInternal('provisioning_operation_id', null);
            $tenant->save();
        }

        app(CentralAuditLogger::class)->log(
            'tenant.provisioning_failed',
            'Background tenant provisioning failed.',
            tenantId: $this->tenantId,
            context: ['operation_id' => $this->operationId, 'error' => $exception?->getMessage()],
        );
    }
}
