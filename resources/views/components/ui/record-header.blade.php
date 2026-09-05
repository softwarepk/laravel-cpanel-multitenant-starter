@props([
    'name',
    'subtitle' => null,
    'initials' => null,
])

<section {{ $attributes->class('overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm shadow-zinc-950/[0.025] dark:border-zinc-800 dark:bg-zinc-950') }}>
    <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
        <div class="flex min-w-0 items-center gap-4">
            @if ($initials)
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 text-sm font-bold tracking-wide text-blue-700 dark:border-blue-900/80 dark:bg-blue-950/50 dark:text-blue-300" aria-hidden="true">
                    {{ $initials }}
                </div>
            @endif

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $name }}</h1>
                    @isset($status)
                        {{ $status }}
                    @endisset
                </div>

                @if ($subtitle)
                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $subtitle }}</div>
                @endif

                @isset($meta)
                    <div class="mt-2 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $meta }}
                    </div>
                @endisset
            </div>
        </div>

        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    @isset($stats)
        <div class="grid divide-y divide-zinc-200 border-t border-zinc-200 bg-zinc-50/55 dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900/45 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            {{ $stats }}
        </div>
    @endisset
</section>
