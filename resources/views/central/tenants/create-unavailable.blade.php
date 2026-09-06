@extends('central.layouts.app', ['title' => 'New Tenant', 'pageTitle' => 'New tenant', 'pageEyebrow' => 'Tenant provisioning'])
@section('content')
<div class="mb-8">
    <a href="{{ route('central.tenants.index') }}" class="text-sm font-medium text-zinc-500">← Back to tenants</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Tenant provisioning is not configured here</h1>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-500">The Control Center creates production tenants through cPanel. This environment is not configured for that workflow.</p>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="space-y-6">
        <section class="rounded-2xl border border-amber-200 bg-amber-50/70 p-6 dark:border-amber-900 dark:bg-amber-950/20">
            <h2 class="font-semibold text-amber-950 dark:text-amber-100">Local development</h2>
            <p class="mt-2 text-sm leading-6 text-amber-900/80 dark:text-amber-200/80">Create a local SQLite tenant from the command line instead. It does not call cPanel, create public DNS, or wait for SSL.</p>
            <div class="mt-4 rounded-xl bg-zinc-950 px-4 py-3 font-mono text-sm text-zinc-100">php artisan tenant:local-create</div>
            <p class="mt-3 text-sm text-amber-900/80 dark:text-amber-200/80">The command creates a tenant such as <span class="font-mono">acme.localhost</span>, runs tenant migrations, and creates the initial tenant administrator.</p>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
            <h2 class="font-semibold">Production cPanel provisioning</h2>
            <p class="mt-1 text-sm text-zinc-500">Configure the production tenant database and cPanel settings, then reload this page.</p>
            <ul class="mt-5 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                @foreach ($missingRequirements as $requirement)
                    <li class="flex gap-2"><span class="mt-1 text-zinc-400">•</span><span class="font-mono text-xs sm:text-sm">{{ $requirement }}</span></li>
                @endforeach
            </ul>
        </section>
    </div>

    <aside class="space-y-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/60">
            <h3 class="font-semibold">Current tenant driver</h3>
            <div class="mt-3 rounded-xl bg-zinc-50 p-3 font-mono text-xs dark:bg-zinc-950">{{ $tenantDriver ?: 'not configured' }}</div>
            <p class="mt-3 text-sm leading-6 text-zinc-500">SQLite is the intended local-development driver. Production cPanel provisioning requires MySQL or MariaDB.</p>
        </div>
    </aside>
</div>
@endsection
