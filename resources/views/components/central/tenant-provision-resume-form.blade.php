@props([
    'tenant',
    'failed' => false,
])

@php
    $suffix = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $tenant->getTenantKey());
    $errorId = 'provision-resume-errors-'.$suffix;
@endphp

<div id="{{ $errorId }}" class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"></div>
<form
    method="POST"
    action="{{ route('central.tenants.retry', $tenant) }}"
    class="mt-4 grid gap-3 sm:grid-cols-2"
    data-provision-resume-form
    data-error-target="{{ $errorId }}"
>
    @csrf
    <input name="admin_name" placeholder="Administrator name" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
    <input name="admin_email" type="email" value="{{ $tenant->initial_admin_email }}" placeholder="Administrator email" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
    <input name="admin_password" type="password" placeholder="Temporary password" autocomplete="new-password" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
    <input name="admin_password_confirmation" type="password" placeholder="Confirm password" autocomplete="new-password" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
    <div class="sm:col-span-2">
        <button type="submit" class="rounded-xl {{ $failed ? 'bg-red-700' : 'bg-blue-700' }} px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {{ $failed ? 'Retry provisioning' : 'Continue provisioning' }}
        </button>
    </div>
</form>
