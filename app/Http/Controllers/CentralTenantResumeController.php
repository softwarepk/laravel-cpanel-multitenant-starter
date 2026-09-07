<?php

namespace App\Http\Controllers;

use App\Actions\Tenants\ProvisionTenant;
use App\Models\Tenant;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Throwable;

class CentralTenantResumeController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, ProvisionTenant $provision, CentralAuditLogger $audit): RedirectResponse|JsonResponse
    {
        $stage = (string) $tenant->provisioning_status;
        $resumable = $stage === 'failed'
            || ($tenant->status === 'provisioning' && ! in_array($stage, ['active', 'https_pending'], true));

        abort_unless($resumable, 409, 'This tenant is not in a provisioning state that can be resumed.');

        $latestDeletion = TenantDeletionRecord::query()
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->latest('id')
            ->first();

        abort_if(
            $latestDeletion instanceof TenantDeletionRecord && $latestDeletion->isUnresolved(),
            409,
            'Provisioning cannot be resumed while permanent-deletion cleanup is unresolved.',
        );

        $request->merge(['admin_email' => strtolower(trim((string) $request->input('admin_email')))]);
        $validated = $request->validate([
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        try {
            $tenant = $provision->handle(
                (string) $tenant->getTenantKey(),
                (string) ($tenant->name ?: $tenant->getTenantKey()),
                $validated['admin_name'],
                $validated['admin_email'],
                $validated['admin_password'],
            );
        } catch (Throwable $e) {
            report($e);
            $audit->log(
                'tenant.provisioning_retry_failed',
                'Tenant provisioning retry failed.',
                tenantId: (string) $tenant->getTenantKey(),
                context: ['error' => $e->getMessage()],
                request: $request,
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tenant provisioning could not be completed.',
                    'errors' => ['provisioning' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['provisioning' => $e->getMessage()]);
        }

        $audit->log(
            'tenant.provisioning_retried',
            'Tenant provisioning retry completed.',
            tenantId: (string) $tenant->getTenantKey(),
            request: $request,
        );

        $redirect = route('central.tenants.show', $tenant);
        $active = $tenant->isActive();
        $message = $active
            ? 'Tenant provisioning completed.'
            : 'Tenant application provisioning completed and is waiting for trusted HTTPS.';

        if ($request->expectsJson()) {
            return response()->json([
                'tenant_id' => (string) $tenant->getTenantKey(),
                'status' => $tenant->status,
                'provisioning_status' => $tenant->provisioning_status,
                'message' => $message,
                'redirect' => $redirect,
            ], $active ? 200 : 202);
        }

        return redirect($redirect)->with('status', $message);
    }
}
