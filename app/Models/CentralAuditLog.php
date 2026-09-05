<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['central_admin_id', 'tenant_id', 'action', 'description', 'context', 'ip_address', 'user_agent'])]
class CentralAuditLog extends Model
{
    public const UPDATED_AT = null;

    public function getConnectionName(): ?string
    {
        return (string) config('tenancy.database.central_connection');
    }

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    /** @return BelongsTo<CentralAdmin, $this> */
    public function centralAdmin(): BelongsTo
    {
        return $this->belongsTo(CentralAdmin::class);
    }
}
