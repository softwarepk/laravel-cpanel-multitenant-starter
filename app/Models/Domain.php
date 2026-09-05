<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    protected $guarded = [];

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
