@props([
    'tenant',
    'failedProvisioning' => false,
])

<div id="deletion-errors" class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"></div>
<form id="tenant-delete-form" method="POST" action="{{ route('central.tenants.destroy', $tenant) }}" class="mt-6 space-y-3 border-t border-zinc-200 pt-5 dark:border-zinc-800" autocomplete="off">
    @csrf
    @method('DELETE')
    <div class="text-sm font-semibold text-red-700">{{ $failedProvisioning ? 'Delete failed tenant' : 'Permanent deletion' }}</div>
    <p class="text-xs leading-5 text-zinc-500">
        @if($failedProvisioning)
            Provisioning did not complete. The application will remove any platform domain, tenant storage, database, or other managed infrastructure that was created before the failure, then remove the central tenant record. Cleanup history is retained under Central Activity.
        @else
            The application will attempt to remove the managed platform domain, custom domains, tenant storage, and tenant database. Cleanup history is retained under Central Activity.
        @endif
    </p>
    <input name="tenant_id_confirmation" placeholder="Type tenant ID: {{ $tenant->id }}" autocomplete="off" required class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
    <div>
        <label for="deletion-current-password" class="mb-1.5 block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Control Center administrator password</label>
        <input id="deletion-current-password" name="current_password" type="password" placeholder="Enter your Control Center admin password" autocomplete="new-password" data-1p-ignore="true" data-lpignore="true" data-bwignore="true" required class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
        <p class="mt-1.5 text-xs leading-5 text-zinc-500">Enter the password for the Control Center administrator account you are currently signed in with. This must be entered manually to confirm permanent deletion.</p>
    </div>
    <button class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white">{{ $failedProvisioning ? 'Delete failed tenant' : 'Permanently delete' }}</button>
</form>
