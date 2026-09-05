<?php

namespace App\Services;

use App\Models\CentralAdmin;
use App\Models\CentralAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CentralAuditLogger
{
    /** @param array<string, mixed> $context */
    public function log(string $action, ?string $description = null, ?string $tenantId = null, array $context = [], ?CentralAdmin $admin = null, ?Request $request = null): CentralAuditLog
    {
        if (! $admin instanceof CentralAdmin) {
            $authenticated = Auth::guard('central')->user();
            $admin = $authenticated instanceof CentralAdmin ? $authenticated : null;
        }

        if (! $request instanceof Request && app()->bound('request')) {
            $request = request();
        }

        return CentralAuditLog::query()->create([
            'central_admin_id' => $admin?->getKey(),
            'tenant_id' => $tenantId,
            'action' => $action,
            'description' => $description,
            'context' => $context === [] ? null : $context,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
