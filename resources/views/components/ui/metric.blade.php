@props([
    'label',
    'value' => null,
    'hint' => null,
    'emphasis' => false,
])

<div {{ $attributes->class(['min-w-0', 'space-y-1.5' => $emphasis, 'space-y-1' => ! $emphasis]) }}>
    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</flux:text>

    @if ($value !== null)
        <div @class([
            'ui-number font-semibold text-zinc-950 dark:text-white',
            'text-2xl tracking-tight sm:text-3xl' => $emphasis,
            'text-lg' => ! $emphasis,
        ])>{{ $value }}</div>
    @else
        {{ $slot }}
    @endif

    @if ($hint)
        <flux:text class="text-xs">{{ $hint }}</flux:text>
    @endif
</div>
