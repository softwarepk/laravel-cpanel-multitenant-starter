<?php

namespace App\Jobs;

use App\Actions\Tenants\DeleteTenant;
use App\Models\CentralAdmin;
use App\Models\Tenant;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeleteTenantJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $deletionRecordId,
        public readonly ?int $centralAdminId,
        public readonly string $restoreStatus = 'suspended',
    ) {}

    public function handle(DeleteTenant $deleteTenant): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $history = TenantDeletionRecord::query()->findOrFail($this->deletionRecordId);

        if ((string) $history->status !== 'started') {
            return;
        }

        $admin = $this->centralAdminId !== null
            ? CentralAdmin::query()->find($this->centralAdminId)
            : null;

        $deleteTenant->handle($tenant, $admin, $history);
    }

    public function failed(?Throwable $exception): void
    {
        $history = TenantDeletionRecord::query()->find($this->deletionRecordId);
        if ($history instanceof TenantDeletionRecord && (string) $history->status === 'started') {
            $history->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);
        }

        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant instanceof Tenant && $tenant->status === 'deleting') {
            $restoreStatus = in_array($this->restoreStatus, ['suspended', 'failed'], true)
                ? $this->restoreStatus
                : ($tenant->provisioning_status === 'failed' ? 'failed' : 'suspended');
            $tenant->update(['status' => $restoreStatus]);
        }

        app(CentralAuditLogger::class)->log(
            'tenant.deletion_failed',
            'Background tenant deletion failed.',
            tenantId: $this->tenantId,
            context: [
                'deletion_record_id' => $this->deletionRecordId,
                'error' => $exception?->getMessage(),
            ],
        );
    }
}
