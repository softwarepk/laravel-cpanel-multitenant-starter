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
        foreach ((array) $this->cleanup_results as $result) {
            if (is_array($result) && ($result['status'] ?? null) === 'failed') {
                return true;
            }
        }

        return false;
    }
}
