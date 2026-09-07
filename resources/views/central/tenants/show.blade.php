@extends('central.layouts.app', ['title' => $tenant->name ?: $tenant->id, 'pageTitle' => $tenant->name ?: $tenant->id, 'pageEyebrow' => 'Tenant workspace'])

@section('content')
@php
    $primary = $tenant->domains->firstWhere('is_primary', true) ?? $tenant->domains->firstWhere('type', 'platform');
    $provisioningInProgress = $tenant->status === 'provisioning' && ! in_array($tenant->provisioning_status, ['active', 'failed'], true);
    $deletionInProgress = $tenant->status === 'deleting' || $unresolvedDeletion?->status === 'started';
    $configurationAvailable = $tenant->provisioning_status === 'active'
        && in_array($tenant->status, ['active', 'suspended'], true)
        && $unresolvedDeletion === null;
@endphp

<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <a href="{{ route('central.tenants.index') }}" class="text-sm text-zinc-500">← Tenants</a>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-semibold tracking-tight">{{ $tenant->name ?: $tenant->id }}</h1>
            <x-ui.status-badge :status="$tenant->status" />
        </div>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500">
            <span class="font-mono">{{ $tenant->id }}</span>
            <span>{{ $primary?->domain ?: 'No domain' }}</span>
            <span class="font-mono">{{ $tenant->database_name ?: 'No database' }}</span>
        </div>
    </div>
    @if($tenant->status === 'active' && $primary && $primary->status === 'active')
        <a href="https://{{ $primary->domain }}" target="_blank" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-semibold dark:border-zinc-700">Open tenant ↗</a>
    @endif
</div>

@if($tenant->provisioning_status === 'failed')
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950/30">
        <div class="font-semibold">Provisioning failed</div>
        <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $tenant->provisioning_error }}</p>
        <form method="POST" action="{{ route('central.tenants.retry', $tenant) }}" class="mt-5 grid gap-3 sm:grid-cols-2">
            @csrf
            <input name="admin_name" placeholder="Administrator name" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
            <input name="admin_email" type="email" value="{{ $tenant->initial_admin_email }}" placeholder="Administrator email" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
            <input name="admin_password" type="password" placeholder="Temporary password" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
            <input name="admin_password_confirmation" type="password" placeholder="Confirm password" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
            <div class="sm:col-span-2"><button class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white">Retry provisioning</button></div>
        </form>
    </div>
@endif

@if($unresolvedDeletion && ! $deletionInProgress)
    <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
        <div class="font-semibold text-amber-900 dark:text-amber-200">Deletion cleanup is unresolved</div>
        <p class="mt-1 text-sm leading-6 text-amber-800 dark:text-amber-300">A previous permanent-deletion attempt did not finish cleanly. Reactivation and tenant configuration are blocked because infrastructure may already have been partially removed. Retry permanent deletion or review deletion record #{{ $unresolvedDeletion->id }} in Central Activity.</p>
    </div>
@endif

<x-ui.blocking-operation id="tenant-provision-progress" scope="tenant-workspace" eyebrow="Provisioning" title="Provisioning tenant" message="Reading current provisioning status…" />
<x-ui.blocking-operation id="tenant-delete-progress" scope="tenant-workspace" eyebrow="Permanent deletion" title="Deleting tenant" message="Reading current deletion status…" />

<div id="tenant-workspace" class="grid gap-6 transition-opacity xl:grid-cols-[minmax(0,1.25fr)_minmax(340px,.75fr)]">
    <div class="space-y-6">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/60">
            <h2 class="font-semibold">Domains</h2>
            <p class="mt-1 text-sm text-zinc-500">The platform hostname is permanent. Verified custom domains can become primary aliases.</p>

            <div class="mt-5 space-y-3">
                @foreach($tenant->domains as $domain)
                    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ $domain->domain }}</span>
                                @if($domain->is_primary)<span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs dark:bg-zinc-800">Primary</span>@endif
                                <x-ui.status-badge :status="$domain->status" />
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">{{ ucfirst($domain->type) }} · DNS {{ $domain->dns_verified_at ? 'verified' : 'pending' }} · cPanel {{ $domain->cpanel_verified_at ? 'verified' : 'pending' }} · SSL {{ $domain->ssl_verified_at ? 'verified' : 'pending' }}</div>
                            @if($domain->verification_error)<div class="mt-1 text-xs text-red-600">{{ $domain->verification_error }}</div>@endif
                        </div>

                        @if($configurationAvailable)
                            <div class="flex flex-wrap gap-2">
                                @if($domain->type === 'custom' && $domain->status !== 'active')
                                    <form method="POST" action="{{ route('central.tenants.domains.verify', [$tenant, $domain]) }}">@csrf<button class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-semibold dark:border-zinc-700">Verify</button></form>
                                @endif
                                @if($domain->status === 'active' && ! $domain->is_primary)
                                    <form method="POST" action="{{ route('central.tenants.domains.primary', [$tenant, $domain]) }}">@csrf<button class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-semibold dark:border-zinc-700">Make primary</button></form>
                                @endif
                                @if($domain->type === 'custom' && ! $domain->is_primary)
                                    <form method="POST" action="{{ route('central.tenants.domains.destroy', [$tenant, $domain]) }}">@csrf @method('DELETE')<button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 dark:border-red-900">Remove</button></form>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($configurationAvailable)
                <form method="POST" action="{{ route('central.tenants.domains.store', $tenant) }}" class="mt-5 flex flex-col gap-3 sm:flex-row">
                    @csrf
                    <input name="domain" placeholder="portal.customer.example" class="min-w-0 flex-1 rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 dark:border-zinc-700 dark:bg-zinc-950" required>
                    <button class="rounded-xl bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-white dark:text-zinc-950">Add custom domain</button>
                </form>
                <p class="mt-2 text-xs text-zinc-500">DNS target: {{ $customDomainDnsTarget ?: 'Not configured' }}</p>
            @elseif($provisioningInProgress)
                <p class="mt-5 rounded-xl bg-zinc-50 px-3 py-2 text-xs leading-5 text-zinc-500 dark:bg-zinc-950">Domain management becomes available after tenant provisioning completes.</p>
            @elseif($deletionInProgress)
                <p class="mt-5 rounded-xl bg-zinc-50 px-3 py-2 text-xs leading-5 text-zinc-500 dark:bg-zinc-950">Domain management is disabled while permanent deletion is queued or running.</p>
            @elseif($unresolvedDeletion)
                <p class="mt-5 rounded-xl bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">Domain management is disabled while deletion cleanup remains unresolved.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/60">
            <h2 class="font-semibold">Recent tenant activity</h2>
            <p class="mt-1 text-sm text-zinc-500">Control-plane operations affecting this tenant.</p>
            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($auditLogs as $log)
                    <div class="py-3">
                        <div class="text-sm font-medium">{{ $log->description ?: $log->action }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ $log->centralAdmin?->name ?: 'System' }} · {{ $log->created_at?->format('Y-m-d H:i') }}</div>
                    </div>
                @empty
                    <div class="py-8 text-sm text-zinc-500">No activity recorded.</div>
                @endforelse
            </div>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/60">
            <h2 class="font-semibold">Tenant details</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs text-zinc-500">Database</dt><dd class="mt-1 break-all font-mono">{{ $tenant->database_name ?: '—' }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Initial administrator</dt><dd class="mt-1">{{ $tenant->initial_admin_email ?: '—' }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Provisioned</dt><dd class="mt-1">{{ $tenant->provisioned_at?->format('Y-m-d H:i') ?: '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/60">
            <h2 class="font-semibold">Lifecycle</h2>

            @if($tenant->status === 'deleting')
                <p class="mt-2 text-sm text-zinc-500">Permanent deletion is queued or running. Tenant configuration is locked until the operation completes or fails.</p>
            @elseif($tenant->provisioning_status === 'failed')
                <p class="mt-2 text-sm text-zinc-500">Provisioning did not complete. You can retry provisioning above, or permanently remove this failed tenant and any partially created infrastructure.</p>
                @if(! $deletionInProgress)
                    <x-central.tenant-delete-form :tenant="$tenant" :failed-provisioning="true" />
                @endif
            @elseif($tenant->provisioning_status !== 'active')
                <p class="mt-2 text-sm text-zinc-500">Lifecycle actions become available after provisioning reaches an operational state.</p>
            @elseif($tenant->status === 'active')
                <p class="mt-2 text-sm text-zinc-500">Suspension immediately blocks tenant hosts without deleting data.</p>
                <form id="tenant-suspend-form" method="POST" action="{{ route('central.tenants.suspend', $tenant) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="confirmed" value="0">
                    <button class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800">Suspend tenant</button>
                </form>
            @elseif($tenant->status === 'suspended')
                <p class="mt-2 text-sm text-zinc-500">The tenant is blocked. It can be reactivated, or permanently deleted after explicit confirmation.</p>

                @if($unresolvedDeletion)
                    <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">Reactivation is disabled while deletion record #{{ $unresolvedDeletion->id }} remains unresolved.</p>
                @else
                    <form method="POST" action="{{ route('central.tenants.activate', $tenant) }}" class="mt-4">@csrf<button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Reactivate tenant</button></form>
                @endif

                @if(! $deletionInProgress)
                    <x-central.tenant-delete-form :tenant="$tenant" />
                @endif
            @endif
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const csrfToken = @json(csrf_token());
    const provisioningActive = @json($provisioningInProgress);
    const provisioningPanelId = 'tenant-provision-progress';
    const provisioningStatusUrl = @json(route('central.tenants.provisioning.status', ['tenantId' => (string) $tenant->getTenantKey()]));
    const httpsCheckUrl = @json(route('central.tenants.https.check', $tenant));
    const provisioningProgress = {starting:5,pending:10,domain:24,database:40,migrating:58,administrator:72,https_pending:88,active:100,failed:100};
    let provisioningTimer = null;

    const showProvisioning = (data = {}) => {
        const stage = data.provisioning_status || 'starting';
        window.ControlCenterOperation.update(provisioningPanelId, {
            eyebrow: stage === 'active' ? 'Ready' : stage === 'failed' ? 'Provisioning failed' : 'Provisioning',
            title: stage === 'active' ? 'Tenant is ready' : stage === 'failed' ? 'Provisioning failed' : 'Provisioning tenant',
            message: data.message || 'Provisioning is in progress…',
            progress: provisioningProgress[stage] ?? 10,
        });
    };

    const pollProvisioning = async () => {
        try {
            const response = await fetch(provisioningStatusUrl, {headers:{Accept:'application/json'}, cache:'no-store'});
            const data = await response.json();
            showProvisioning(data);

            if (data.provisioning_status === 'active') {
                window.clearTimeout(provisioningTimer);
                window.setTimeout(() => window.location.reload(), 500);
                return;
            }

            if (data.provisioning_status === 'failed') {
                window.clearTimeout(provisioningTimer);
                window.setTimeout(() => window.location.reload(), 500);
                return;
            }

            if (data.provisioning_status === 'https_pending') {
                try {
                    const httpsResponse = await fetch(httpsCheckUrl, {
                        method: 'POST',
                        headers: {Accept:'application/json', 'X-CSRF-TOKEN':csrfToken},
                    });
                    const httpsData = await httpsResponse.json();
                    showProvisioning(httpsData);
                    if (httpsData.ready) {
                        window.setTimeout(() => window.location.reload(), 500);
                        return;
                    }
                } catch (_) {
                    // HTTPS may still be propagating; the next poll will retry.
                }
            }
        } catch (_) {
            // A transient status request failure should not stop progress monitoring.
        }

        provisioningTimer = window.setTimeout(pollProvisioning, 1500);
    };

    if (provisioningActive) {
        window.ControlCenterOperation.begin(provisioningPanelId, {
            eyebrow: 'Provisioning',
            title: 'Provisioning tenant',
            message: 'Reading current provisioning status…',
            progress: 5,
        });
        provisioningTimer = window.setTimeout(pollProvisioning, 100);
    }

    const deletionForm = document.getElementById('tenant-delete-form');
    const deletionPanelId = 'tenant-delete-progress';
    const deletionErrors = document.getElementById('deletion-errors');
    const deletionStatusUrl = @json(route('central.tenants.deletion.status', ['tenantId' => (string) $tenant->getTenantKey()]));
    const resumeDeletion = @json($deletionInProgress);
    let deletionBusy = false;
    let deletionRequestInFlight = false;
    let deletionTimer = null;

    const showDeletion = (data = {}) => {
        window.ControlCenterOperation.update(deletionPanelId, {
            eyebrow: data.completed ? 'Deletion complete' : data.failed ? 'Deletion failed' : 'Permanent deletion',
            title: data.completed ? 'Tenant deleted' : data.failed ? 'Deletion failed' : 'Deleting tenant',
            message: data.message || 'Removing tenant infrastructure…',
            progress: data.progress ?? 5,
        });
    };

    const scheduleDeletionPoll = (delay = 900) => {
        if (!deletionBusy) return;
        window.clearTimeout(deletionTimer);
        deletionTimer = window.setTimeout(pollDeletion, delay);
    };

    const finishDeletion = (url, message) => {
        deletionBusy = false;
        deletionRequestInFlight = false;
        window.clearTimeout(deletionTimer);
        showDeletion({completed:true, progress:100, message:message || 'Tenant deletion completed. Opening the tenant list…'});
        window.setTimeout(() => window.location.assign(url || @json(route('central.tenants.index'))), 650);
    };

    const failDeletion = (message, reload = false) => {
        if (!deletionBusy) return;
        deletionBusy = false;
        deletionRequestInFlight = false;
        window.clearTimeout(deletionTimer);
        window.ControlCenterOperation.end(deletionPanelId);
        if (deletionErrors) {
            deletionErrors.textContent = message || 'Tenant deletion failed.';
            deletionErrors.classList.remove('hidden');
        }
        if (reload) window.setTimeout(() => window.location.reload(), 500);
    };

    const pollDeletion = async () => {
        if (!deletionBusy) return;

        try {
            const response = await fetch(deletionStatusUrl, {headers:{Accept:'application/json'}, cache:'no-store'});
            const data = await response.json();
            if (! (deletionRequestInFlight && data.failed)) showDeletion(data);
            if (data.completed) return finishDeletion(data.redirect, data.message);
            if (data.failed && ! deletionRequestInFlight) return failDeletion(data.message, true);
        } catch (_) {
            // A transient status request failure should not interrupt deletion monitoring.
        }

        scheduleDeletionPoll();
    };

    if (deletionForm) {
        deletionForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (deletionBusy) return;

            if (deletionErrors) {
                deletionErrors.textContent = '';
                deletionErrors.classList.add('hidden');
            }

            deletionBusy = true;
            deletionRequestInFlight = true;
            window.ControlCenterOperation.begin(deletionPanelId, {
                eyebrow: 'Permanent deletion',
                title: 'Deleting tenant',
                message: 'Validating confirmation and queueing permanent deletion…',
                progress: 5,
            });
            scheduleDeletionPoll(150);

            try {
                const response = await fetch(deletionForm.action, {
                    method: 'POST',
                    body: new FormData(deletionForm),
                    headers: {Accept:'application/json'},
                });
                const data = await response.json();
                deletionRequestInFlight = false;

                if (!response.ok) throw data;
                showDeletion(data);
                scheduleDeletionPoll(250);
            } catch (data) {
                deletionRequestInFlight = false;
                const message = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'Tenant deletion failed.');
                failDeletion(message);
            }
        });
    }

    if (resumeDeletion) {
        deletionBusy = true;
        deletionRequestInFlight = false;
        window.ControlCenterOperation.begin(deletionPanelId, {
            eyebrow: 'Permanent deletion',
            title: 'Deleting tenant',
            message: 'Reading current deletion progress…',
            progress: 10,
        });
        scheduleDeletionPoll(100);
    }
})();
</script>
@endpush
