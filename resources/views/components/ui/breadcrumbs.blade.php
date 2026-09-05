@props([
    'items' => [],
])

<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->class('text-sm text-zinc-500 dark:text-zinc-400') }}>
    <ol class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
        @foreach ($items as $item)
            <li class="flex min-w-0 items-center gap-2">
                @if (! $loop->last && filled($item['href'] ?? null))
                    <a
                        class="truncate font-medium text-zinc-600 transition hover:text-blue-700 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:text-zinc-300 dark:hover:text-blue-300 dark:focus-visible:ring-offset-zinc-950"
                        href="{{ $item['href'] }}"
                        wire:navigate
                    >
                        {{ $item['label'] }}
                    </a>
                    <span class="text-zinc-300 dark:text-zinc-700" aria-hidden="true">/</span>
                @else
                    <span class="truncate font-medium text-zinc-900 dark:text-zinc-100" @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
