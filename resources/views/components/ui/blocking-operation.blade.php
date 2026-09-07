@props([
    'id',
    'scope' => null,
    'eyebrow' => 'Working',
    'title' => 'Please wait',
    'message' => 'This operation may take a few moments.',
    'detail' => 'Keep this page open until the operation completes.',
])

<div
    id="{{ $id }}"
    @if($scope) data-operation-scope="{{ $scope }}" @endif
    class="fixed inset-0 z-[100] hidden overflow-y-auto bg-zinc-950/45 p-4 backdrop-blur-sm sm:p-6"
    role="status"
    aria-live="polite"
    aria-hidden="true"
    tabindex="-1"
>
    <div class="mx-auto flex min-h-full max-w-xl items-center justify-center">
        <div class="w-full rounded-3xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
            <div class="flex items-start gap-4">
                <div data-operation-spinner class="mt-1 flex size-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                    <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 0-9-9v3a6 6 0 0 1 6 6h3Z"></path>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div data-operation-eyebrow class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600 dark:text-blue-300">{{ $eyebrow }}</div>
                    <div data-operation-title class="mt-1 text-2xl font-semibold tracking-tight">{{ $title }}</div>
                    <div data-operation-message class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $message }}</div>
                </div>
            </div>

            <div class="mt-6 h-2.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="5">
                <div data-operation-bar class="h-full w-[5%] rounded-full bg-blue-600 transition-all duration-500"></div>
            </div>
            <div data-operation-detail class="mt-3 text-xs leading-5 text-zinc-500">{{ $detail }}</div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                if (window.ControlCenterOperation) return;

                const active = new Map();
                let previousOverflow = '';

                const panelFor = (id) => document.getElementById(id);
                const clampProgress = (value) => Math.max(0, Math.min(100, Number(value) || 0));
                const hasActiveOperation = () => active.size > 0;

                const beforeUnload = (event) => {
                    if (!hasActiveOperation()) return;
                    event.preventDefault();
                    event.returnValue = '';
                };

                window.addEventListener('beforeunload', beforeUnload);

                document.addEventListener('keydown', (event) => {
                    if (!hasActiveOperation()) return;
                    if (event.key === 'Tab' || event.key === 'Escape') event.preventDefault();
                }, true);

                document.addEventListener('click', (event) => {
                    if (!hasActiveOperation()) return;
                    const current = Array.from(active.keys()).map(panelFor).find((panel) => panel && !panel.classList.contains('hidden'));
                    if (current && !current.contains(event.target)) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                }, true);

                const update = (id, data = {}) => {
                    const panel = panelFor(id);
                    if (!panel) return;

                    if (data.eyebrow !== undefined) panel.querySelector('[data-operation-eyebrow]').textContent = data.eyebrow;
                    if (data.title !== undefined) panel.querySelector('[data-operation-title]').textContent = data.title;
                    if (data.message !== undefined) panel.querySelector('[data-operation-message]').textContent = data.message;
                    if (data.detail !== undefined) panel.querySelector('[data-operation-detail]').textContent = data.detail;

                    if (data.progress !== undefined) {
                        const progress = clampProgress(data.progress);
                        const bar = panel.querySelector('[data-operation-bar]');
                        const progressbar = bar?.parentElement;
                        if (bar) bar.style.width = `${progress}%`;
                        if (progressbar) progressbar.setAttribute('aria-valuenow', String(progress));
                    }

                    const spinner = panel.querySelector('[data-operation-spinner]');
                    if (spinner && data.complete !== undefined) spinner.classList.toggle('opacity-40', Boolean(data.complete));
                };

                const begin = (id, data = {}) => {
                    const panel = panelFor(id);
                    if (!panel) return;

                    const scopeId = panel.dataset.operationScope;
                    const scope = scopeId ? document.getElementById(scopeId) : null;
                    if (scope) {
                        scope.inert = true;
                        scope.classList.add('opacity-60');
                    }

                    if (!hasActiveOperation()) {
                        previousOverflow = document.documentElement.style.overflow;
                        document.documentElement.style.overflow = 'hidden';
                    }

                    active.set(id, scope);
                    panel.classList.remove('hidden');
                    panel.setAttribute('aria-hidden', 'false');
                    update(id, data);
                    panel.focus({preventScroll: true});
                };

                const end = (id) => {
                    const panel = panelFor(id);
                    if (panel) {
                        panel.classList.add('hidden');
                        panel.setAttribute('aria-hidden', 'true');
                    }

                    const scope = active.get(id);
                    if (scope) {
                        scope.inert = false;
                        scope.classList.remove('opacity-60');
                    }
                    active.delete(id);

                    if (!hasActiveOperation()) {
                        document.documentElement.style.overflow = previousOverflow;
                    }
                };

                window.ControlCenterOperation = {begin, update, end, active: hasActiveOperation};
            })();
        </script>
    @endpush
@endonce
