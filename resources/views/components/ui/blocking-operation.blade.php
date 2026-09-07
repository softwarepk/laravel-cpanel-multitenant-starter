@props([
    'id',
    'scope' => null,
    'eyebrow' => 'Working',
    'title' => 'Please wait',
    'message' => 'This operation may take a few moments.',
])

<div
    id="{{ $id }}"
    @if($scope) data-operation-scope="{{ $scope }}" @endif
    class="hidden mb-6 rounded-2xl border border-blue-200 bg-blue-50/70 p-5 shadow-sm dark:border-blue-900 dark:bg-blue-950/20"
    role="status"
    aria-live="polite"
    aria-hidden="true"
>
    <div data-operation-eyebrow class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-600">{{ $eyebrow }}</div>
    <div data-operation-title class="mt-1 text-xl font-semibold">{{ $title }}</div>
    <div data-operation-message class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $message }}</div>
    <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/80 dark:bg-zinc-800" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="5">
        <div data-operation-bar class="h-full w-[5%] rounded-full bg-blue-600 transition-all duration-500"></div>
    </div>
    <div data-operation-detail class="mt-3 text-xs text-zinc-500">Actions in this workspace are temporarily disabled. You can continue using other areas of the Control Center.</div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                if (window.ControlCenterOperation) return;

                const activeScopes = new Map();
                const panelFor = (id) => document.getElementById(id);
                const clampProgress = (value) => Math.max(0, Math.min(100, Number(value) || 0));

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
                };

                const begin = (id, data = {}) => {
                    const panel = panelFor(id);
                    if (!panel) return;

                    const scopeId = panel.dataset.operationScope;
                    const scope = scopeId ? document.getElementById(scopeId) : null;
                    if (scope) {
                        scope.inert = true;
                        scope.classList.add('opacity-60');
                        activeScopes.set(id, scope);
                    }

                    panel.classList.remove('hidden');
                    panel.setAttribute('aria-hidden', 'false');
                    update(id, data);
                };

                const end = (id) => {
                    const panel = panelFor(id);
                    if (panel) {
                        panel.classList.add('hidden');
                        panel.setAttribute('aria-hidden', 'true');
                    }

                    const scope = activeScopes.get(id);
                    if (scope) {
                        scope.inert = false;
                        scope.classList.remove('opacity-60');
                        activeScopes.delete(id);
                    }
                };

                window.ControlCenterOperation = { begin, update, end };
            })();
        </script>
    @endpush
@endonce
