@props(['tenant'])

@php($dialogId = 'suspend-tenant-'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $tenant->getTenantKey()))

<button
    type="button"
    class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
    data-suspend-open="{{ $dialogId }}"
>
    Suspend tenant
</button>

<dialog
    id="{{ $dialogId }}"
    class="w-[min(92vw,520px)] rounded-3xl border border-zinc-200 bg-white p-0 text-zinc-950 shadow-2xl backdrop:bg-zinc-950/50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
    style="position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);margin:0;"
>
    <div class="p-6 sm:p-7">
        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">Tenant suspension</div>
        <h3 class="mt-2 text-2xl font-semibold tracking-tight">Suspend {{ $tenant->name ?: $tenant->id }}?</h3>
        <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
            Users will immediately lose access to this tenant. No tenant data will be deleted, and the tenant can be reactivated later.
        </p>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold dark:border-zinc-700" data-suspend-close="{{ $dialogId }}">Cancel</button>
            <form method="POST" action="{{ route('central.tenants.suspend', $tenant) }}">
                @csrf
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="w-full rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white sm:w-auto">Confirm suspension</button>
            </form>
        </div>
    </div>
</dialog>

@once
    @push('scripts')
        <script>
            (() => {
                document.querySelectorAll('[data-suspend-open]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const dialog = document.getElementById(button.dataset.suspendOpen);
                        if (dialog?.showModal) dialog.showModal();
                    });
                });

                document.querySelectorAll('[data-suspend-close]').forEach((button) => {
                    button.addEventListener('click', () => document.getElementById(button.dataset.suspendClose)?.close());
                });

                document.querySelectorAll('dialog[id^="suspend-tenant-"]').forEach((dialog) => {
                    dialog.addEventListener('click', (event) => {
                        if (event.target === dialog) dialog.close();
                    });
                });
            })();
        </script>
    @endpush
@endonce
