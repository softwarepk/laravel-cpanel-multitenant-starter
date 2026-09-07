<?php

namespace App\Http\Controllers;

use App\Actions\Tenants\ProvisionTenant;
use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rules\Password;
use Throwable;

class QueuedTenantProvisioningController extends Controller
{
    private const int STALE_PROVISIONING_MINUTES = 10;

    public function store(Request $request, ProvisionTenant $provision, CentralAuditLogger $audit): RedirectResponse|JsonResponse
    {
        $request->merge([
            'id' => strtolower(trim((string) $request->input('id'))),
            'admin_email' => strtolower(trim((string) $request->input('admin_email'))),
        ]);

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', 'unique:tenants,id'],
            'name' => ['required', 'string', 'max:120'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', Password::default(), 'confirmed'],
        ], [
            'id.regex' => 'The tenant ID may contain lowercase letters, numbers, and hyphens only, and must start and end with a letter or number.',
        ]);

        try {
            $tenant = $provision->reserve($validated['id'], $validated['name'], $validated['admin_email']);
            ProvisionTenantJob::dispatch(
                (string) $tenant->getTenantKey(),
                $validated['admin_name'],
                $validated['admin_email'],
                Crypt::encryptString($validated['admin_password']),
            );
        } catch (Throwable $e) {
            report($e);
            $audit->log('tenant.provisioning_failed', 'Tenant provisioning could not be queued.', tenantId: $validated['id'], context: ['name' => $validated['name'], 'error' => $e->getMessage()], request: $request);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tenant provisioning could not be queued.', 'errors' => ['provisioning' => [$e->getMessage()]]], 422);
            }

            return back()->withInput($request->except(['admin_password', 'admin_password_confirmation']))->withErrors(['provisioning' => $e->getMessage()]);
        }

        $audit->log('tenant.provisioning_queued', 'Tenant provisioning queued for background processing.', tenantId: (string) $tenant->getTenantKey(), context: ['name' => $tenant->name, 'database' => $tenant->database_name], request: $request);
        $redirect = route('central.tenants.show', $tenant);

        if ($request->expectsJson()) {
            return response()->json([
                'tenant_id' => (string) $tenant->getTenantKey(),
                'status' => $tenant->status,
                'provisioning_status' => $tenant->provisioning_status,
                'message' => 'Tenant provisioning has been queued.',
                'redirect' => $redirect,
            ], 202);
        }

        return redirect($redirect)->with('status', "Tenant {$tenant->name} provisioning has been queued.");
    }

    public function retry(Request $request, Tenant $tenant, CentralAuditLogger $audit): RedirectResponse
    {
        abort_unless($tenant->provisioning_status === 'failed', 409);
        $request->merge(['admin_email' => strtolower(trim((string) $request->input('admin_email')))]);
        $validated = $request->validate([
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $tenant->update([
            'status' => 'provisioning',
            'provisioning_status' => 'pending',
            'provisioning_error' => null,
            'initial_admin_email' => $validated['admin_email'],
        ]);

        ProvisionTenantJob::dispatch(
            (string) $tenant->getTenantKey(),
            $validated['admin_name'],
            $validated['admin_email'],
            Crypt::encryptString($validated['admin_password']),
        );

        $audit->log('tenant.provisioning_retry_queued', 'Tenant provisioning retry queued for background processing.', tenantId: (string) $tenant->getTenantKey(), request: $request);

        return redirect()->route('central.tenants.show', $tenant)->with('status', 'Provisioning retry queued.');
    }

    public function provisioningStatus(string $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->with('domains')->find($tenantId);
        if ($tenant === null) {
            return response()->json(['tenant_id' => $tenantId, 'status' => 'starting', 'provisioning_status' => 'starting', 'message' => 'Reserving tenant identity…']);
        }

        if ($this->provisioningIsStale($tenant)) {
            $tenant->update([
                'status' => 'failed',
                'provisioning_status' => 'failed',
                'provisioning_error' => 'Background provisioning stopped reporting progress before completion. Retry provisioning to continue safely from the persisted infrastructure state.',
            ]);
            $tenant->refresh();
        }

        $platform = $tenant->domains->firstWhere('type', 'platform');

        return response()->json([
            'tenant_id' => (string) $tenant->getTenantKey(),
            'status' => $tenant->status,
            'provisioning_status' => $tenant->provisioning_status,
            'message' => $this->provisioningMessage((string) $tenant->provisioning_status),
            'error' => $tenant->provisioning_error,
            'domain' => $platform?->domain,
            'https_ready' => $platform?->ssl_verified_at !== null,
            'redirect' => route('central.tenants.show', $tenant),
        ]);
    }

    private function provisioningIsStale(Tenant $tenant): bool
    {
        if ($tenant->status !== 'provisioning' || ! in_array((string) $tenant->provisioning_status, ['pending', 'domain', 'database', 'migrating', 'administrator'], true)) {
            return false;
        }

        return $tenant->updated_at !== null && $tenant->updated_at->lt(now()->subMinutes(self::STALE_PROVISIONING_MINUTES));
    }

    private function provisioningMessage(string $status): string
    {
        return match ($status) {
            'pending' => 'Queued for background provisioning…',
            'domain' => 'Provisioning permanent platform hostname…',
            'database' => 'Creating isolated tenant database…',
            'migrating' => 'Preparing the tenant database…',
            'administrator' => 'Creating the initial tenant administrator…',
            'https_pending' => 'Waiting for cPanel to issue a trusted HTTPS certificate…',
            'active' => 'Tenant is ready.',
            'failed' => 'Provisioning failed.',
            default => 'Provisioning is in progress…',
        };
    }
}
