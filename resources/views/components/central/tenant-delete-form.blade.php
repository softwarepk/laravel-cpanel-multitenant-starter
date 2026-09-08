@props([
    'tenant',
    'failedProvisioning' => false,
    'compact' => false,
])

@php
    $suffix = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $tenant->getTenantKey());
    $errorId = 'deletion-errors-'.$suffix.'-'.($failedProvisioning ? 'failed' : 'lifecycle');
@endphp

<div id="{{ $errorId }}" class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"></div>
<form
    method="POST"
    action="{{ route('central.tenants.destroy', $tenant) }}"
    class="{{ $compact ? 'mt-4' : 'mt-6 border-t border-zinc-200 pt-5 dark:border-zinc-800' }} space-y-3"
    autocomplete="off"
    data-delete-form
    data-error-target="{{ $errorId }}"
>
    @csrf
    @method('DELETE')
    <div class="text-sm font-semibold text-red-700">{{ $failedProvisioning ? 'Delete failed tenant' : 'Permanent deletion' }}</div>
    <p class="text-xs leading-5 text-zinc-500">
        @if($failedProvisioning)
            Provisioning did not complete. Cleanup will remove any managed platform domain, custom domains, tenant storage, and tenant database that exist before removing the central tenant record.
        @else
            Cleanup runs synchronously. Keep this page open while managed domains, tenant storage, and the isolated tenant database are removed. Cleanup history is retained under Central Activity.
        @endif
    </p>
    <input name="tenant_id_confirmation" placeholder="Type tenant ID: {{ $tenant->id }}" autocomplete="off" required class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
    <div>
        <label class="mb-1.5 block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Control Center administrator password</label>
        <input name="current_password" type="password" placeholder="Enter your Control Center admin password" autocomplete="new-password" data-1p-ignore="true" data-lpignore="true" data-bwignore="true" required class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
        <p class="mt-1.5 text-xs leading-5 text-zinc-500">The tenant ID and your current Control Center password are the permanent-deletion confirmation. No browser popup is used.</p>
    </div>
    <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{{ $failedProvisioning ? 'Permanently clean up failed tenant' : 'Permanently delete tenant' }}</button>
</form>
