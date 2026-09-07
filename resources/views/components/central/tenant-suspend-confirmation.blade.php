@props(['tenant'])

<details class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
    <summary class="cursor-pointer list-none font-semibold text-amber-900 dark:text-amber-200">
        Suspend tenant
        <span class="ml-1 text-xs font-normal text-amber-700 dark:text-amber-300">— confirmation required</span>
    </summary>

    <div class="mt-4 border-t border-amber-200 pt-4 dark:border-amber-800">
        <div class="font-semibold text-zinc-950 dark:text-white">Confirm tenant suspension</div>
        <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-200">
            Users will immediately lose access to this tenant. No tenant data will be deleted, and the tenant can be reactivated later.
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('central.tenants.suspend', $tenant) }}">
                @csrf
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white">
                    Confirm suspension
                </button>
            </form>

            <a href="{{ route('central.tenants.show', $tenant) }}" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                Cancel
            </a>
        </div>
    </div>
</details>
