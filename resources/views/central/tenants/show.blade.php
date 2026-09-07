@extends('central.layouts.app', ['title' => $tenant->name ?: $tenant->id, 'pageTitle' => $tenant->name ?: $tenant->id, 'pageEyebrow' => 'Tenant workspace'])

@section('content')
@php
    $primary = $tenant->domains->firstWhere('is_primary', true) ?? $tenant->domains->firstWhere('type', 'platform');
    $stage = (string) $tenant->provisioning_status;
    $provisioningInProgress = $tenant->status === 'provisioning' && ! in_array($stage, ['active', 'https_pending', 'failed'], true);
    $httpsPending = $stage === 'https_pending';
    $provisioningFailed = $stage === 'failed';
    $deletionInProgress = $unresolvedDeletion?->status === 'started';
    $configurationAvailable = $stage === 'active'
        && in_array($tenant->status, ['active', 'suspended'], true)
        && $unresolvedDeletion === null;
    $stageProgress = match ($stage) {
        'pending' => 10,
        'domain' => 24,
        'database' => 40,
        'migrating' => 58,
        'administrator' => 72,
        'https_pending' => 88,
        'active', 'failed' => 100,
        default => 8,
    };
    $stageMessage = match ($stage) {
        'pending' => 'Reserving tenant identity…',
        'domain' => 'Provisioning permanent platform hostname…',
        'database' => 'Creating isolated tenant database…',
        'migrating' => 'Preparing the tenant database…',
        'administrator' => 'Creating the initial tenant administrator…',
        'https_pending' => 'Waiting for cPanel to issue a trusted HTTPS certificate…',
        'failed' => 'Provisioning failed.',
        default => 'Provisioning is in progress…',
    };
@endphp

<x-ui.blocking-operation
    id="tenant-provision-operation"
    scope="tenant-operation-scope"
    eyebrow="Tenant provisioning"
    title="Provisioning tenant"
    message="Reading current provisioning status…"
    detail="Keep this page open while the synchronous operation completes."
/>
<x-ui.blocking-operation
    id="tenant-delete-operation"
    scope="tenant-operation-scope"
    eyebrow="Permanent deletion"
    title="Deleting tenant"
    message="Starting permanent deletion…"
    detail="Keep this page open while managed tenant infrastructure is removed."
/>

<div id="tenant-operation-scope" class="transition-opacity">
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

    @if($provisioningInProgress)
        <div id="provisioning-recovery-card" class="mb-6 rounded-2xl border border-blue-200 bg-blue-50/70 p-5 shadow-sm dark:border-blue-900 dark:bg-blue-950/20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-600">Provisioning</div>
                    <div id="page-provision-title" class="mt-1 text-xl font-semibold">Provisioning is not finished</div>
                    <p id="page-provision-message" class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $stageMessage }}</p>
                </div>
                <button id="check-provisioning-status" type="button" class="shrink-0 rounded-xl border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 dark:bg-zinc-900 dark:text-blue-200">Check status now</button>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/80 dark:bg-zinc-800">
                <div id="page-provision-bar" class="h-full rounded-full bg-blue-600 transition-all duration-500" style="width: {{ $stageProgress }}%"></div>
            </div>
            <p class="mt-3 text-xs leading-5 text-zinc-500">This page checks the saved provisioning stage automatically. If you left the original page and the stage is no longer advancing, you can safely continue the idempotent provisioning sequence below.</p>

            <details class="mt-4 rounded-xl border border-blue-200 bg-white/70 p-4 dark:border-blue-900 dark:bg-zinc-900/50">
                <summary class="cursor-pointer text-sm font-semibold text-blue-800 dark:text-blue-200">Continue provisioning if the previous request was interrupted</summary>
                <p class="mt-2 text-xs leading-5 text-zinc-500">Re-enter the tenant administrator details. Existing domain and database resources are checked and reused where safe.</p>
                <x-central.tenant-provision-resume-form :tenant="$tenant" />
            </details>
        </div>
    @endif

    @if($httpsPending)
        <div id="provisioning-recovery-card" class="mb-6 rounded-2xl border border-blue-200 bg-blue-50/70 p-5 shadow-sm dark:border-blue-900 dark:bg-blue-950/20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-600">Provisioning · final step</div>
                    <div id="page-provision-title" class="mt-1 text-xl font-semibold">Waiting for trusted HTTPS</div>
                    <p id="page-provision-message" class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">The database, schema, and administrator are ready. This page will keep checking the permanent platform hostname and activate the tenant when its certificate is trusted.</p>
                </div>
                <button id="check-provisioning-status" type="button" class="shrink-0 rounded-xl border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 dark:bg-zinc-900 dark:text-blue-200">Check HTTPS now</button>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/80 dark:bg-zinc-800">
                <div id="page-provision-bar" class="h-full rounded-full bg-blue-600 transition-all duration-500" style="width: 88%"></div>
            </div>
            <p class="mt-3 text-xs text-zinc-500">Automatic recheck runs while this page is open; you can also use the button above at any time.</p>
        </div>
    @endif

    @if($provisioningFailed)
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950/30">
            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-red-700 dark:text-red-300">Provisioning failed</div>
            <div class="mt-1 text-xl font-semibold">Tenant setup did not complete</div>
            <p class="mt-2 text-sm leading-6 text-red-700 dark:text-red-300">{{ $tenant->provisioning_error }}</p>
            <x-central.tenant-provision-resume-form :tenant="$tenant" :failed="true" />

            <details class="mt-5 border-t border-red-200 pt-4 dark:border-red-900">
                <summary class="cursor-pointer text-sm font-semibold text-red-800 dark:text-red-200">Clean up this failed tenant instead</summary>
                <p class="mt-2 text-xs leading-5 text-zinc-600 dark:text-zinc-300">Use this only when you do not want to retry provisioning. The guarded cleanup below removes managed resources synchronously and records each cleanup result.</p>
                <x-central.tenant-delete-form :tenant="$tenant" :failed-provisioning="true" :compact="true" />
            </details>
        </div>
    @endif

    @if($deletionInProgress)
        <div id="deletion-recovery-card" class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700 dark:text-amber-300">Permanent deletion</div>
                    <div class="mt-1 text-xl font-semibold text-amber-950 dark:text-amber-100">Deletion cleanup is still marked in progress</div>
                    <p id="page-deletion-message" class="mt-2 text-sm leading-6 text-amber-900 dark:text-amber-200">Checking the latest cleanup record…</p>
                </div>
                <button id="check-deletion-status" type="button" class="shrink-0 rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900 dark:bg-zinc-900 dark:text-amber-200">Check deletion status</button>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/80 dark:bg-zinc-800">
                <div id="page-deletion-bar" class="h-full w-[8%] rounded-full bg-amber-600 transition-all duration-500"></div>
            </div>
            <p class="mt-3 text-xs leading-5 text-amber-800 dark:text-amber-300">If the previous browser request was interrupted and the cleanup no longer advances, the status check will say so. You can then retry the guarded deletion below.</p>
            <details class="mt-4 rounded-xl border border-amber-200 bg-white/70 p-4 dark:border-amber-900 dark:bg-zinc-900/50">
                <summary class="cursor-pointer text-sm font-semibold text-amber-900 dark:text-amber-200">Retry deletion if the previous request was interrupted</summary>
                <x-central.tenant-delete-form :tenant="$tenant" :failed-provisioning="$tenant->status === 'failed'" :compact="true" />
            </details>
        </div>
    @elseif($unresolvedDeletion)
        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
            <div class="font-semibold text-amber-900 dark:text-amber-200">Deletion cleanup is unresolved</div>
            <p class="mt-1 text-sm leading-6 text-amber-800 dark:text-amber-300">A previous permanent-deletion attempt failed before the central tenant record could be removed. Reactivation and tenant configuration remain blocked until cleanup is retried or manually resolved. Review deletion record #{{ $unresolvedDeletion->id }} in Central Activity.</p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(340px,.75fr)]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/60">
                <h2 class="font-semibold">Domains</h2>
                <p class="mt-1 text-sm text-zinc-500">The platform hostname is permanent. Verified custom domains can become primary aliases. Custom domains created here can also be removed from cPanel from this page.</p>

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
                @else
                    <p class="mt-5 rounded-xl bg-zinc-50 px-3 py-2 text-xs leading-5 text-zinc-500 dark:bg-zinc-950">Domain configuration is locked until provisioning is active and no deletion cleanup is unresolved.</p>
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

                @if($tenant->status === 'active')
                    <p class="mt-2 text-sm text-zinc-500">Suspension immediately blocks tenant hosts without deleting data.</p>
                    <x-central.tenant-suspend-confirmation :tenant="$tenant" />
                @elseif($tenant->status === 'suspended')
                    <p class="mt-2 text-sm text-zinc-500">The tenant is blocked. It can be reactivated, or permanently deleted after explicit in-page confirmation.</p>
                    @if($unresolvedDeletion)
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">Reactivation is disabled while deletion record #{{ $unresolvedDeletion->id }} remains unresolved.</p>
                    @else
                        <form method="POST" action="{{ route('central.tenants.activate', $tenant) }}" class="mt-4">@csrf<button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Reactivate tenant</button></form>
                    @endif
                    @if(! $deletionInProgress)
                        <x-central.tenant-delete-form :tenant="$tenant" />
                    @endif
                @elseif($provisioningFailed)
                    <p class="mt-2 text-sm text-zinc-500">Provisioning did not complete. Retry provisioning or clean up the failed tenant using the guarded options above.</p>
                @else
                    <p class="mt-2 text-sm text-zinc-500">Lifecycle actions become available after provisioning reaches an operational state.</p>
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    if (!window.ControlCenterOperation) return;

    const csrfToken = @json(csrf_token());
    const tenantId = @json((string) $tenant->getTenantKey());
    const provisioningStatusUrl = @json(route('central.tenants.provisioning.status', ['tenantId' => (string) $tenant->getTenantKey()]));
    const httpsCheckUrl = @json(route('central.tenants.https.check', $tenant));
    const deletionStatusUrl = @json(route('central.tenants.deletion.status', ['tenantId' => (string) $tenant->getTenantKey()]));
    const tenantIndexUrl = @json(route('central.tenants.index'));
    const pageProvisioningActive = @json($provisioningInProgress || $httpsPending);
    const pageDeletionActive = @json($deletionInProgress);
    const provisionProgress = {starting:5,pending:10,domain:24,database:40,migrating:58,administrator:72,https_pending:88,active:100,failed:100};

    let provisionPageTimer = null;
    let deletionPageTimer = null;
    let provisionOverlayTimer = null;
    let deletionOverlayTimer = null;
    let provisionOverlayActive = false;
    let deletionOverlayActive = false;

    const jsonFrom = async (response) => {
        try {
            return await response.json();
        } catch (_) {
            return {message: response.ok ? 'Request completed.' : 'The server returned an unexpected response.'};
        }
    };

    const showFormError = (form, data, fallback) => {
        const target = document.getElementById(form.dataset.errorTarget || '');
        if (!target) return;
        target.textContent = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || fallback);
        target.classList.remove('hidden');
        target.scrollIntoView({behavior:'smooth', block:'center'});
    };

    const checkHttps = async () => {
        const response = await fetch(httpsCheckUrl, {
            method:'POST',
            headers:{Accept:'application/json', 'X-CSRF-TOKEN':csrfToken},
            cache:'no-store',
        });
        return jsonFrom(response);
    };

    const fetchProvisioning = async () => {
        const response = await fetch(provisioningStatusUrl, {headers:{Accept:'application/json'}, cache:'no-store'});
        return jsonFrom(response);
    };

    const updatePageProvisioning = (data = {}) => {
        const stage = data.provisioning_status || 'starting';
        const title = document.getElementById('page-provision-title');
        const message = document.getElementById('page-provision-message');
        const bar = document.getElementById('page-provision-bar');
        if (title) title.textContent = stage === 'https_pending' ? 'Waiting for trusted HTTPS' : stage === 'failed' ? 'Provisioning failed' : 'Provisioning is not finished';
        if (message) message.textContent = data.message || 'Provisioning is in progress…';
        if (bar) bar.style.width = `${provisionProgress[stage] ?? 8}%`;
    };

    const refreshProvisioningPage = async (manual = false) => {
        if (!pageProvisioningActive && !manual) return;
        try {
            let data = await fetchProvisioning();
            updatePageProvisioning(data);

            if (data.provisioning_status === 'active' || data.provisioning_status === 'failed') {
                return window.location.reload();
            }

            if (data.provisioning_status === 'https_pending') {
                data = await checkHttps();
                updatePageProvisioning(data);
                if (data.provisioning_status === 'active') return window.location.reload();
            }
        } catch (_) {
            const message = document.getElementById('page-provision-message');
            if (message && manual) message.textContent = 'Status could not be checked just now. Try again in a moment.';
        }

        if (pageProvisioningActive) provisionPageTimer = window.setTimeout(() => refreshProvisioningPage(false), 5000);
    };

    document.getElementById('check-provisioning-status')?.addEventListener('click', () => {
        if (provisionPageTimer) window.clearTimeout(provisionPageTimer);
        refreshProvisioningPage(true);
    });

    const updateProvisionOverlay = (data = {}) => {
        const stage = data.provisioning_status || 'starting';
        window.ControlCenterOperation.update('tenant-provision-operation', {
            eyebrow: stage === 'active' ? 'Tenant ready' : stage === 'failed' ? 'Provisioning failed' : 'Tenant provisioning',
            title: stage === 'active' ? 'Tenant is ready' : stage === 'failed' ? 'Provisioning failed' : 'Provisioning tenant',
            message: data.message || 'Provisioning is in progress…',
            progress: provisionProgress[stage] ?? 8,
            complete: stage === 'active' || stage === 'failed',
            detail: stage === 'active'
                ? 'Provisioning completed. Refreshing the tenant workspace…'
                : 'Keep this page open for live progress. Closing it no longer cancels the server-side synchronous request.',
        });
    };

    const pollProvisionOverlay = async () => {
        if (!provisionOverlayActive) return;
        try {
            let data = await fetchProvisioning();
            updateProvisionOverlay(data);
            if (data.provisioning_status === 'active') {
                provisionOverlayActive = false;
                return window.setTimeout(() => window.location.reload(), 300);
            }
            if (data.provisioning_status === 'failed') {
                provisionOverlayActive = false;
                window.ControlCenterOperation.end('tenant-provision-operation');
                return window.location.reload();
            }
            if (data.provisioning_status === 'https_pending') {
                data = await checkHttps();
                updateProvisionOverlay(data);
                if (data.provisioning_status === 'active') {
                    provisionOverlayActive = false;
                    return window.setTimeout(() => window.location.reload(), 300);
                }
            }
        } catch (_) {
            // Keep the main synchronous request running even if a status read briefly fails.
        }
        if (provisionOverlayActive) provisionOverlayTimer = window.setTimeout(pollProvisionOverlay, 1500);
    };

    document.querySelectorAll('form[data-provision-resume-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            const error = document.getElementById(form.dataset.errorTarget || '');
            if (error) error.classList.add('hidden');
            provisionOverlayActive = true;
            window.ControlCenterOperation.begin('tenant-provision-operation', {
                eyebrow:'Tenant provisioning',
                title:'Continuing provisioning',
                message:'Rechecking existing tenant resources and continuing from a safe point…',
                progress:8,
            });
            provisionOverlayTimer = window.setTimeout(pollProvisionOverlay, 350);

            try {
                const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}});
                const data = await jsonFrom(response);
                if (!response.ok) throw data;
                updateProvisionOverlay(data);
                if (data.provisioning_status === 'active') {
                    provisionOverlayActive = false;
                    if (provisionOverlayTimer) window.clearTimeout(provisionOverlayTimer);
                    return window.setTimeout(() => window.location.reload(), 300);
                }
            } catch (data) {
                provisionOverlayActive = false;
                if (provisionOverlayTimer) window.clearTimeout(provisionOverlayTimer);
                window.ControlCenterOperation.end('tenant-provision-operation');
                showFormError(form, data, 'Provisioning could not be completed.');
            }
        });
    });

    const updateDeletionPage = (data = {}) => {
        const message = document.getElementById('page-deletion-message');
        const bar = document.getElementById('page-deletion-bar');
        if (message) message.textContent = data.message || 'Permanent deletion is in progress…';
        if (bar) bar.style.width = `${Number(data.progress) || 8}%`;
    };

    const fetchDeletion = async () => {
        const response = await fetch(deletionStatusUrl, {headers:{Accept:'application/json'}, cache:'no-store'});
        return jsonFrom(response);
    };

    const refreshDeletionPage = async (manual = false) => {
        if (!pageDeletionActive && !manual) return;
        try {
            const data = await fetchDeletion();
            updateDeletionPage(data);
            if (data.redirect) return window.location.assign(data.redirect);
            if (data.status === 'failed') return window.location.reload();
        } catch (_) {
            const message = document.getElementById('page-deletion-message');
            if (message && manual) message.textContent = 'Deletion status could not be checked just now. Try again in a moment.';
        }
        if (pageDeletionActive) deletionPageTimer = window.setTimeout(() => refreshDeletionPage(false), 3000);
    };

    document.getElementById('check-deletion-status')?.addEventListener('click', () => {
        if (deletionPageTimer) window.clearTimeout(deletionPageTimer);
        refreshDeletionPage(true);
    });

    const updateDeletionOverlay = (data = {}) => {
        window.ControlCenterOperation.update('tenant-delete-operation', {
            eyebrow: data.status === 'completed' || data.status === 'completed_with_warnings' ? 'Deletion complete' : data.status === 'failed' ? 'Deletion failed' : 'Permanent deletion',
            title: data.status === 'completed' || data.status === 'completed_with_warnings' ? 'Tenant deleted' : data.status === 'failed' ? 'Deletion failed' : 'Deleting tenant',
            message: data.message || 'Permanent deletion is in progress…',
            progress: Number(data.progress) || 5,
            complete: ['completed','completed_with_warnings','failed'].includes(data.status),
            detail: data.stale
                ? 'The cleanup record has not advanced recently. The request may have been interrupted.'
                : 'Keep this page open while the application removes managed tenant infrastructure.',
        });
    };

    const pollDeletionOverlay = async () => {
        if (!deletionOverlayActive) return;
        try {
            const data = await fetchDeletion();
            updateDeletionOverlay(data);
            if (data.redirect) {
                deletionOverlayActive = false;
                return window.setTimeout(() => window.location.assign(data.redirect), 300);
            }
            if (data.status === 'failed') {
                deletionOverlayActive = false;
                window.ControlCenterOperation.end('tenant-delete-operation');
                return window.location.reload();
            }
        } catch (_) {
            // The DELETE request remains authoritative; keep polling while it runs.
        }
        if (deletionOverlayActive) deletionOverlayTimer = window.setTimeout(pollDeletionOverlay, 1200);
    };

    document.querySelectorAll('form[data-delete-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            const error = document.getElementById(form.dataset.errorTarget || '');
            if (error) error.classList.add('hidden');
            deletionOverlayActive = true;
            window.ControlCenterOperation.begin('tenant-delete-operation', {
                eyebrow:'Permanent deletion',
                title:'Deleting tenant',
                message:'Starting guarded tenant cleanup…',
                progress:5,
            });
            deletionOverlayTimer = window.setTimeout(pollDeletionOverlay, 300);

            try {
                const response = await fetch(form.action, {
                    method:'POST',
                    body:new FormData(form),
                    headers:{Accept:'application/json'},
                });
                const data = await jsonFrom(response);
                if (!response.ok) throw data;

                deletionOverlayActive = false;
                if (deletionOverlayTimer) window.clearTimeout(deletionOverlayTimer);
                updateDeletionOverlay({status:'completed', progress:100, message:data.message || 'Tenant deletion completed.'});
                return window.setTimeout(() => window.location.assign(data.redirect || tenantIndexUrl), 350);
            } catch (data) {
                deletionOverlayActive = false;
                if (deletionOverlayTimer) window.clearTimeout(deletionOverlayTimer);
                window.ControlCenterOperation.end('tenant-delete-operation');
                showFormError(form, data, 'Tenant deletion could not be completed.');
            }
        });
    });

    if (pageProvisioningActive) provisionPageTimer = window.setTimeout(() => refreshProvisioningPage(false), 700);
    if (pageDeletionActive) deletionPageTimer = window.setTimeout(() => refreshDeletionPage(false), 700);
})();
</script>
@endpush
