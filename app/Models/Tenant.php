<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property-read Collection<int, Domain> $domains
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected function casts(): array
    {
        return [
            'provisioned_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /** @return HasMany<Domain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    /** @return list<string> */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'status',
            'provisioning_status',
            'database_name',
            'initial_admin_email',
            'provisioning_error',
            'provisioned_at',
            'suspended_at',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->provisioning_status === 'active';
    }
}
