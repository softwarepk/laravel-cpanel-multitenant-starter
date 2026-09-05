@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class('ui-section') }}>
    @if ($title || $description || isset($action))
        <div class="ui-section-header flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-1">
                @if ($title)
                    <flux:heading size="lg" class="!font-semibold">{{ $title }}</flux:heading>
                @endif
                @if ($description)
                    <flux:text class="max-w-3xl !leading-5 !text-zinc-600 dark:!text-zinc-300">{{ $description }}</flux:text>
                @endif
            </div>
            @isset($action)
                <div class="shrink-0">{{ $action }}</div>
            @endisset
        </div>
    @endif

    <div class="ui-section-body">
        {{ $slot }}
    </div>
</section>
