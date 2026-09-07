@props([
    'id',
    'eyebrow' => 'Working',
    'title' => 'Please wait',
    'message' => 'This operation may take a few moments.',
])

<div
    id="{{ $id }}"
    class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-live="polite"
    aria-hidden="true"
    tabindex="-1"
>
    <div class="absolute inset-0 bg-zinc-950/55 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
        <div data-operation-eyebrow class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-600">{{ $eyebrow }}</div>
        <div data-operation-title class="mt-1 text-xl font-semibold">{{ $title }}</div>
        <div data-operation-message class="mt-2 text-sm leading-6 text-zinc-500">{{ $message }}</div>
        <div class="mt-5 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="5">
            <div data-operation-bar class="h-full w-[5%] rounded-full bg-blue-600 transition-all duration-500"></div>
        </div>
        <div data-operation-detail class="mt-3 text-xs text-zinc-400">Please keep this page open. Page actions are temporarily disabled while this operation is running.</div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                if (window.ControlCenterOperation) return;

                let appShell = null;

                const overlayFor = (id) => document.getElementById(id);
                const clampProgress = (value) => Math.max(0, Math.min(100, Number(value) || 0));

                const update = (id, data = {}) => {
                    const overlay = overlayFor(id);
                    if (!overlay) return;

                    if (data.eyebrow !== undefined) overlay.querySelector('[data-operation-eyebrow]').textContent = data.eyebrow;
                    if (data.title !== undefined) overlay.querySelector('[data-operation-title]').textContent = data.title;
                    if (data.message !== undefined) overlay.querySelector('[data-operation-message]').textContent = data.message;
                    if (data.detail !== undefined) overlay.querySelector('[data-operation-detail]').textContent = data.detail;

                    if (data.progress !== undefined) {
                        const progress = clampProgress(data.progress);
                        const bar = overlay.querySelector('[data-operation-bar]');
                        const progressbar = bar?.parentElement;
                        if (bar) bar.style.width = `${progress}%`;
                        if (progressbar) progressbar.setAttribute('aria-valuenow', String(progress));
                    }
                };

                const begin = (id, data = {}) => {
                    const overlay = overlayFor(id);
                    if (!overlay) return;

                    if (overlay.parentElement !== document.body) document.body.appendChild(overlay);

                    appShell = document.body.querySelector(':scope > .min-h-screen');
                    if (appShell && appShell !== overlay) appShell.inert = true;

                    document.body.classList.add('overflow-hidden');
                    overlay.classList.remove('hidden');
                    overlay.setAttribute('aria-hidden', 'false');
                    update(id, data);
                    window.setTimeout(() => overlay.focus(), 0);
                };

                const end = (id) => {
                    const overlay = overlayFor(id);
                    if (overlay) {
                        overlay.classList.add('hidden');
                        overlay.setAttribute('aria-hidden', 'true');
                    }

                    if (appShell) appShell.inert = false;
                    document.body.classList.remove('overflow-hidden');
                    appShell = null;
                };

                window.ControlCenterOperation = { begin, update, end };
            })();
        </script>
    @endpush
@endonce
