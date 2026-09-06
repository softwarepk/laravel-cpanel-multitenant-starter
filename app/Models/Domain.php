<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

#[Fillable([
    'domain',
    'tenant_id',
    'type',
    'status',
    'is_primary',
    'dns_verified_at',
    'cpanel_verified_at',
    'ssl_verified_at',
    'verification_error',
])]
class Domain extends BaseDomain
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'dns_verified_at' => 'datetime',
            'cpanel_verified_at' => 'datetime',
            'ssl_verified_at' => 'datetime',
        ];
    }
}
