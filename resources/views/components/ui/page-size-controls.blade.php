@props([
    'paginator',
    'perPage',
])

<div class="space-y-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        @if ($paginator->total() >= 20)
            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Show') }}</span>
                @foreach ([20, 50, 100] as $size)
                    <button
                        type="button"
                        wire:click="$set('perPage', {{ $size }})"
                        @class([
                            'rounded-md px-2.5 py-1.5 font-medium transition',
                            'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $perPage === $size,
                            'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => $perPage !== $size,
                        ])
                        aria-pressed="{{ $perPage === $size ? 'true' : 'false' }}"
                    >
                        {{ $size }}
                    </button>
                @endforeach
            </div>
        @else
            <div></div>
        @endif

        @if ($paginator->hasPages())
            <div>
                {{ $paginator->links() }}
            </div>
        @else
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                @if ($paginator->total() > 0)
                    {{ __('Showing :first to :last of :total results', [
                        'first' => number_format($paginator->firstItem()),
                        'last' => number_format($paginator->lastItem()),
                        'total' => number_format($paginator->total()),
                    ]) }}
                @else
                    {{ __('Showing 0 results') }}
                @endif
            </div>
        @endif
    </div>
</div>
