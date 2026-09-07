<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'tenant_name',
    'database_name',
    'platform_domain',
    'custom_domains',
    'central_admin_id',
    'status',
    'cleanup_results',
    'completed_at',
])]
class TenantDeletionRecord extends Model
{
    public function getConnectionName(): ?string
    {
        return (string) config('tenancy.database.central_connection');
    }

    protected function casts(): array
    {
        return [
            'custom_domains' => 'array',
            'cleanup_results' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CentralAdmin, $this> */
    public function centralAdmin(): BelongsTo
    {
        return $this->belongsTo(CentralAdmin::class);
    }

    public function hasCleanupFailures(): bool
    {
        /** @var array<string, array{status:string,target:string|null,error?:string}> $results */
        $results = (array) $this->cleanup_results;

        foreach ($results as $result) {
            if ($result['status'] === 'failed') {
                return true;
            }
        }

        return false;
    }

    public function isUnresolved(): bool
    {
        return in_array((string) $this->status, ['started', 'failed'], true);
    }

    public function blocksIdentityReuse(): bool
    {
        return (string) $this->status !== 'completed' || $this->hasCleanupFailures();
    }
}
