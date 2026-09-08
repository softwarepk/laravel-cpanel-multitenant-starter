@extends('central.layouts.app', ['title' => 'New Tenant', 'pageTitle' => 'New tenant', 'pageEyebrow' => 'Tenant provisioning'])

@section('content')
<x-ui.blocking-operation
    id="tenant-provision-progress"
    scope="tenant-provision-scope"
    eyebrow="Tenant provisioning"
    title="Provisioning tenant"
    message="Starting tenant provisioning…"
    detail="Keep this page open. The operation continues on the server if the browser disconnects, but remaining here gives you live progress."
/>

<div id="tenant-provision-scope" class="transition-opacity">
    <div class="mb-8">
        <a href="{{ route('central.tenants.index') }}" class="text-sm font-medium text-zinc-500">← Back to tenants</a>
        <h1 class="mt-4 text-3xl font-semibold tracking-tight">Provision a new organization</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-500">The control plane creates the permanent platform hostname, isolated database, tenant schema, initial administrator, and waits for trusted HTTPS before activation.</p>
    </div>

    <div id="provision-errors" class="mb-6 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"></div>

    <form id="tenant-provision-form" method="POST" action="{{ route('central.tenants.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        @csrf
        <div class="space-y-6">
            <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
                <h2 class="font-semibold">Organization</h2>
                <p class="mt-1 text-sm text-zinc-500">Core identity used by the control plane.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium" for="name">Organization name</label>
                        <input id="name" name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 dark:border-zinc-700 dark:bg-zinc-950" placeholder="Acme Corporation">
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="id">Tenant ID</label>
                        <input id="id" name="id" value="{{ old('id') }}" required class="mt-2 w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 font-mono dark:border-zinc-700 dark:bg-zinc-950" placeholder="acme">
                        <p class="mt-2 text-xs text-zinc-500">Lowercase letters, numbers, and hyphens. This becomes part of the permanent hostname.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
                <h2 class="font-semibold">Initial tenant administrator</h2>
                <p class="mt-1 text-sm text-zinc-500">Created inside this tenant's own database.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><label class="text-sm font-medium" for="admin_name">Name</label><input id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required class="mt-2 w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 dark:border-zinc-700 dark:bg-zinc-950"></div>
                    <div><label class="text-sm font-medium" for="admin_email">Email</label><input id="admin_email" type="email" name="admin_email" value="{{ old('admin_email') }}" required class="mt-2 w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 dark:border-zinc-700 dark:bg-zinc-950"></div>
                    <div><label class="text-sm font-medium" for="admin_password">Temporary password</label><input id="admin_password" type="password" name="admin_password" required class="mt-2 w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 dark:border-zinc-700 dark:bg-zinc-950"><p class="mt-2 text-xs text-zinc-500">Password must be {{ implode(', ', $passwordRequirements) }}.</p></div>
                    <div><label class="text-sm font-medium" for="admin_password_confirmation">Confirm password</label><input id="admin_password_confirmation" type="password" name="admin_password_confirmation" required class="mt-2 w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 dark:border-zinc-700 dark:bg-zinc-950"></div>
                </div>
            </section>
            <div class="flex justify-end gap-3"><a href="{{ route('central.tenants.index') }}" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-semibold dark:border-zinc-700">Cancel</a><button id="provision-submit" class="rounded-xl bg-zinc-950 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-white dark:text-zinc-950">Provision tenant</button></div>
        </div>
        <aside class="space-y-4"><div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/60"><h3 class="font-semibold">Permanent platform URL</h3><p class="mt-2 text-sm leading-6 text-zinc-500">Every tenant gets a permanent hostname. Custom domains are optional aliases.</p><div class="mt-4 rounded-xl bg-zinc-50 p-3 font-mono text-xs dark:bg-zinc-950">&lt;tenant-id&gt;.{{ $platformDomain ?: 'platform-domain-not-configured' }}</div></div><div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/60"><h3 class="font-semibold">Provisioning sequence</h3><ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-zinc-500"><li>Reserve tenant identity</li><li>Create platform hostname</li><li>Create isolated database</li><li>Run tenant migrations</li><li>Create tenant administrator</li><li>Confirm trusted HTTPS</li><li>Activate tenant</li></ol></div></aside>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('tenant-provision-form');
    if (!form) return;

    const operationId = 'tenant-provision-progress';
    const errors = document.getElementById('provision-errors');
    const submit = document.getElementById('provision-submit');
    const csrfToken = form.querySelector('[name=_token]').value;
    const statusBase = @json(url('/central/tenants/provisioning'));
    const httpsBase = @json(url('/central/tenants'));
    const progress = {starting:5,pending:10,domain:24,database:40,migrating:58,administrator:72,https_pending:88,active:100,failed:100};

    let tenantId = null;
    let redirectUrl = null;
    let pollTimer = null;
    let operationActive = false;

    const operation = () => window.ControlCenterOperation;

    const stopPolling = () => {
        if (pollTimer) window.clearTimeout(pollTimer);
        pollTimer = null;
    };

    const showStage = (data = {}) => {
        const stage = data.provisioning_status || 'starting';
        const ready = stage === 'active';
        const failed = stage === 'failed';
        operation()?.update(operationId, {
            eyebrow: ready ? 'Tenant ready' : failed ? 'Provisioning failed' : 'Tenant provisioning',
            title: ready ? 'Tenant is ready' : failed ? 'Provisioning failed' : 'Provisioning tenant',
            message: data.message || (stage === 'starting' ? 'Reserving tenant identity…' : 'Provisioning is in progress…'),
            progress: progress[stage] ?? 10,
            complete: ready || failed,
            detail: ready
                ? 'Provisioning completed. Opening the tenant workspace…'
                : failed
                    ? 'Provisioning stopped. The tenant workspace will show retry and cleanup options.'
                    : 'Keep this page open for live progress. Closing it no longer cancels the server-side synchronous request.',
        });
    };

    const finishWithError = (data) => {
        operationActive = false;
        stopPolling();
        operation()?.end(operationId);
        const text = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'Provisioning failed.');
        errors.textContent = text;
        errors.classList.remove('hidden');
        submit.disabled = false;
        errors.scrollIntoView({behavior:'smooth', block:'center'});
    };

    const checkHttps = async () => {
        if (!tenantId) return null;
        const response = await fetch(`${httpsBase}/${encodeURIComponent(tenantId)}/https/check`, {
            method: 'POST',
            headers: {Accept:'application/json', 'X-CSRF-TOKEN':csrfToken},
            cache: 'no-store',
        });
        return response.json();
    };

    const poll = async () => {
        if (!operationActive || !tenantId) return;
        try {
            const response = await fetch(`${statusBase}/${encodeURIComponent(tenantId)}`, {
                headers:{Accept:'application/json'},
                cache:'no-store',
            });
            const data = await response.json();
            redirectUrl = data.redirect || redirectUrl;
            showStage(data);

            if (data.provisioning_status === 'active') {
                operationActive = false;
                stopPolling();
                return window.setTimeout(() => window.location.assign(redirectUrl), 350);
            }

            if (data.provisioning_status === 'failed') {
                finishWithError({message: data.message || 'Provisioning failed. Open the tenant workspace to retry or clean up the failed tenant.'});
                return;
            }

            if (data.provisioning_status === 'https_pending') {
                const httpsData = await checkHttps();
                if (httpsData) {
                    redirectUrl = httpsData.redirect || redirectUrl;
                    showStage(httpsData);
                    if (httpsData.provisioning_status === 'active') {
                        operationActive = false;
                        stopPolling();
                        return window.setTimeout(() => window.location.assign(redirectUrl), 350);
                    }
                }
            }
        } catch (_) {
            // A temporary status-check failure should not interrupt the main synchronous request.
        }

        if (operationActive) pollTimer = window.setTimeout(poll, 1500);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;

        errors.classList.add('hidden');
        submit.disabled = true;
        tenantId = form.querySelector('[name=id]').value.trim().toLowerCase();
        operationActive = true;

        operation()?.begin(operationId, {
            eyebrow:'Tenant provisioning',
            title:'Provisioning tenant',
            message:'Reserving tenant identity…',
            progress:5,
            detail:'Keep this page open for live progress. Closing it no longer cancels the server-side synchronous request.',
        });

        pollTimer = window.setTimeout(poll, 400);

        try {
            const response = await fetch(form.action, {
                method:'POST',
                body:new FormData(form),
                headers:{Accept:'application/json'},
            });
            const data = await response.json();
            if (!response.ok) throw data;

            tenantId = data.tenant_id || tenantId;
            redirectUrl = data.redirect || redirectUrl;
            showStage(data);

            if (data.provisioning_status === 'active') {
                operationActive = false;
                stopPolling();
                return window.setTimeout(() => window.location.assign(redirectUrl), 350);
            }

            if (!pollTimer) pollTimer = window.setTimeout(poll, 500);
        } catch (data) {
            finishWithError(data);
        }
    });
})();
</script>
@endpush