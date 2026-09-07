<?php

namespace App\Http\Controllers;

use App\Contracts\CustomDomainDeprovisioner;
use App\Contracts\CustomDomainProvisioner;
use App\Contracts\CustomDomainVerifier;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantDeletionRecord;
use App\Services\CentralAuditLogger;
use App\Services\PlatformHttpsVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class CentralTenantController extends Controller
{
    public function checkPlatformHttps(Request $request, Tenant $tenant, PlatformHttpsVerifier $https, CentralAuditLogger $audit): JsonResponse|RedirectResponse
    {
        $this->ensureNoUnresolvedDeletion($tenant);
        $platform = $tenant->domains()->where('type', 'platform')->firstOrFail();

        if ($tenant->isActive()) {
            if ($platform->ssl_verified_at !== null) {
                return $request->expectsJson()
                    ? response()->json(['ready' => true, 'status' => 'active', 'provisioning_status' => 'active', 'redirect' => route('central.tenants.show', $tenant)])
                    : back()->with('status', 'HTTPS is already ready.');
            }

            $ready = $https->isReady($platform->domain);
            if ($ready) {
                $platform->update(['status' => 'active', 'ssl_verified_at' => now()]);
                $audit->log('tenant.platform_https_reverified', 'Trusted HTTPS certificate confirmed for an active tenant.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $platform->domain], request: $request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'ready' => $ready,
                    'status' => 'active',
                    'provisioning_status' => 'active',
                    'message' => $ready ? 'Trusted HTTPS is ready.' : 'HTTPS could not be confirmed. No tenant lifecycle or domain state was changed.',
                    'redirect' => route('central.tenants.show', $tenant),
                ], $ready ? 200 : 202);
            }

            return back()->with('status', $ready ? 'Trusted HTTPS is ready.' : 'HTTPS could not be confirmed. No tenant lifecycle or domain state was changed.');
        }

        abort_unless(
            $tenant->status === 'provisioning' && $tenant->provisioning_status === 'https_pending',
            409,
            'HTTPS activation is only available while tenant provisioning is waiting for HTTPS.',
        );

        if (! $https->isReady($platform->domain)) {
            $platform->update(['status' => 'pending', 'ssl_verified_at' => null]);

            return $request->expectsJson()
                ? response()->json(['ready' => false, 'status' => 'provisioning', 'provisioning_status' => 'https_pending', 'message' => 'Waiting for cPanel to issue a trusted HTTPS certificate…'], 202)
                : back()->with('status', 'HTTPS certificate is still pending. The tenant will remain unavailable on the platform hostname until it is trusted.');
        }

        $platform->update(['status' => 'active', 'ssl_verified_at' => now(), 'is_primary' => true]);
        $tenant->update(['status' => 'active', 'provisioning_status' => 'active', 'provisioning_error' => null, 'provisioned_at' => now(), 'suspended_at' => null]);
        $audit->log('tenant.platform_https_ready', 'Trusted HTTPS certificate confirmed; tenant activated.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $platform->domain], request: $request);

        return $request->expectsJson()
            ? response()->json(['ready' => true, 'status' => 'active', 'provisioning_status' => 'active', 'message' => 'Trusted HTTPS is ready. Tenant activated.', 'redirect' => route('central.tenants.show', $tenant)])
            : back()->with('status', 'Trusted HTTPS is ready. Tenant activated.');
    }

    public function addCustomDomain(Request $request, Tenant $tenant, CustomDomainProvisioner $provisioner, CentralAuditLogger $audit): RedirectResponse
    {
        $this->ensureTenantConfigurationMutable($tenant);
        $request->merge(['domain' => strtolower(trim((string) $request->input('domain')))]);
        $validated = $request->validate([
            'domain' => [
                'required', 'string', 'max:253',
                'regex:/^(?=.{1,253}\\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                Rule::notIn(config('tenancy.central_domains', [])), 'unique:domains,domain',
            ],
        ], [
            'domain.regex' => 'The custom domain must be a hostname only, without a scheme, port, path, spaces, or other URL components.',
            'domain.not_in' => 'The custom domain cannot be one of the central administration domains.',
        ]);

        $platformDomain = strtolower(trim((string) config('central.platform.domain')));
        $domain = $validated['domain'];
        if ($platformDomain !== '' && ($domain === $platformDomain || str_ends_with($domain, '.'.$platformDomain))) {
            return back()->withErrors(['domain' => 'Platform-managed hostnames cannot be added as custom domains.']);
        }

        $custom = $tenant->domains()->create(['domain' => $domain, 'type' => 'custom', 'status' => 'pending', 'is_primary' => false]);
        $audit->log('tenant.custom_domain_added', 'Custom domain added and awaiting verification.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $custom->domain], request: $request);

        try {
            $provisioner->ensureCustomDomainReady($custom->domain);
        } catch (Throwable $e) {
            report($e);
            $message = $this->customDomainProvisioningMessage($e);
            $custom->update(['cpanel_verified_at' => null, 'verification_error' => $message]);
            $audit->log('tenant.custom_domain_cpanel_failed', 'Custom domain could not be registered in cPanel.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $custom->domain, 'error' => $e->getMessage()], request: $request);

            return back()->withErrors(['custom_domain_provisioning' => "Custom domain {$custom->domain} was saved as pending. {$message}"]);
        }

        $custom->update(['cpanel_verified_at' => now(), 'verification_error' => null]);
        $audit->log('tenant.custom_domain_cpanel_ready', 'Custom domain registered in cPanel with the application document root.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $custom->domain], request: $request);

        return back()->with('status', "Custom domain {$custom->domain} is registered with the application. Configure its DNS target, allow HTTPS to become ready, then click Verify.");
    }

    public function verifyCustomDomain(Request $request, Tenant $tenant, Domain $domain, CustomDomainProvisioner $provisioner, CustomDomainVerifier $verifier, CentralAuditLogger $audit): RedirectResponse
    {
        $this->ensureTenantConfigurationMutable($tenant);
        $this->ensureDomainBelongsToTenant($domain, $tenant);
        abort_unless($domain->type === 'custom', 409);
        $wasCpanelReady = $domain->cpanel_verified_at !== null;

        try {
            $provisioner->ensureCustomDomainReady($domain->domain);
        } catch (Throwable $e) {
            report($e);
            $message = $this->customDomainProvisioningMessage($e);
            $domain->update(['cpanel_verified_at' => null, 'verification_error' => $message, 'status' => 'pending']);
            $audit->log('tenant.custom_domain_cpanel_failed', 'Custom domain could not be registered in cPanel.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $domain->domain, 'error' => $e->getMessage()], request: $request);

            return back()->withErrors(['domain_verification' => $message]);
        }

        if (! $wasCpanelReady) {
            $domain->update(['cpanel_verified_at' => now(), 'verification_error' => null]);
            $audit->log('tenant.custom_domain_cpanel_ready', 'Custom domain registered in cPanel with the application document root.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $domain->domain], request: $request);
        }

        $result = $verifier->verify($domain->domain);
        $now = now();
        $active = $result['dns'] && $result['cpanel'] && $result['ssl'];
        $domain->update([
            'dns_verified_at' => $result['dns'] ? $now : null,
            'cpanel_verified_at' => $result['cpanel'] ? $now : null,
            'ssl_verified_at' => $result['ssl'] ? $now : null,
            'verification_error' => $result['errors'] === [] ? null : implode(' ', $result['errors']),
            'status' => $active ? 'active' : 'pending',
        ]);
        $audit->log($active ? 'tenant.custom_domain_activated' : 'tenant.custom_domain_verification_failed', $active ? 'Custom domain verified and activated.' : 'Custom domain verification is still pending.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $domain->domain, 'dns' => $result['dns'], 'cpanel' => $result['cpanel'], 'ssl' => $result['ssl'], 'errors' => $result['errors']], request: $request);
        if (! $active) {
            return back()->withErrors(['domain_verification' => $domain->verification_error ?: 'Custom domain verification is not complete.']);
        }

        return back()->with('status', "Custom domain {$domain->domain} is active.");
    }

    public function makePrimaryDomain(Request $request, Tenant $tenant, Domain $domain, CentralAuditLogger $audit): RedirectResponse
    {
        $this->ensureTenantConfigurationMutable($tenant);
        $this->ensureDomainBelongsToTenant($domain, $tenant);
        abort_unless($domain->status === 'active', 409);
        DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($tenant, $domain): void {
            $tenant->domains()->update(['is_primary' => false]);
            $domain->update(['is_primary' => true]);
        });
        $audit->log('tenant.primary_domain_changed', 'Tenant primary domain changed.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $domain->domain, 'type' => $domain->type], request: $request);

        return back()->with('status', "Primary domain changed to {$domain->domain}. The permanent platform domain remains active.");
    }

    public function deleteCustomDomain(Request $request, Tenant $tenant, Domain $domain, CustomDomainDeprovisioner $deprovisioner, CentralAuditLogger $audit): RedirectResponse
    {
        $this->ensureTenantConfigurationMutable($tenant);
        $this->ensureDomainBelongsToTenant($domain, $tenant);
        abort_unless($domain->type === 'custom', 409);
        abort_if($domain->is_primary, 409, 'Make another active domain primary before removing this custom domain.');

        try {
            $deprovisioner->deleteCustomDomain($domain->domain);
        } catch (Throwable $e) {
            report($e);
            $audit->log('tenant.custom_domain_removal_failed', 'Custom domain could not be removed from cPanel.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $domain->domain, 'error' => $e->getMessage()], request: $request);

            return back()->withErrors(['custom_domain_removal' => 'The custom domain remains registered because cPanel cleanup failed: '.$e->getMessage()]);
        }

        $domainName = $domain->domain;
        $domain->delete();
        $audit->log('tenant.custom_domain_removed', 'Custom domain removed from cPanel and the tenant.', tenantId: (string) $tenant->getTenantKey(), context: ['domain' => $domainName], request: $request);

        return back()->with('status', "Custom domain {$domainName} was removed.");
    }

    private function ensureTenantConfigurationMutable(Tenant $tenant): void
    {
        abort_unless(
            $tenant->provisioning_status === 'active' && in_array($tenant->status, ['active', 'suspended'], true),
            409,
            'Tenant configuration is unavailable while provisioning, failed, or being deleted.',
        );

        $this->ensureNoUnresolvedDeletion($tenant);
    }

    private function ensureNoUnresolvedDeletion(Tenant $tenant): void
    {
        $latestDeletion = TenantDeletionRecord::query()
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->latest('id')
            ->first();

        abort_if(
            $latestDeletion instanceof TenantDeletionRecord && $latestDeletion->isUnresolved(),
            409,
            'Tenant configuration is unavailable while deletion cleanup is unresolved.',
        );
    }

    private function ensureDomainBelongsToTenant(Domain $domain, Tenant $tenant): void
    {
        abort_unless((string) $domain->tenant_id === (string) $tenant->getTenantKey(), 404);
    }

    private function customDomainProvisioningMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        $normalized = strtolower($message);
        if (str_contains($normalized, 'already owned by another user')) {
            return 'This hostname cannot be registered automatically because its parent domain is hosted under another cPanel account on the same shared server. The tenant can continue using its permanent platform URL or use a hostname whose parent domain is hosted elsewhere.';
        }
        if (str_contains($normalized, 'dns entry for') && str_contains($normalized, 'already exists')) {
            return 'A DNS record for this hostname already exists in the cPanel DNS cluster. Remove that hostname record temporarily, retry registration, then create the DNS record after registration succeeds.';
        }

        return 'cPanel registration failed: '.$message;
    }
}
