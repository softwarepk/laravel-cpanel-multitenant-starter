@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<header {{ $attributes->class('ui-page-header flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between') }}>
    <div class="min-w-0 space-y-1.5">
        @if ($eyebrow)
            <div class="ui-eyebrow">{{ $eyebrow }}</div>
        @endif
        <flux:heading size="xl" class="!text-[1.65rem] !font-semibold tracking-tight">{{ $title }}</flux:heading>
        @if ($description)
            <flux:text class="max-w-3xl !text-[0.95rem] !leading-6 !text-zinc-600 dark:!text-zinc-300">{{ $description }}</flux:text>
        @endif
        @isset($meta)
            <div class="ui-page-meta">{{ $meta }}</div>
        @endisset
    </div>

    @isset($actions)
        <div class="ui-page-actions flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>
