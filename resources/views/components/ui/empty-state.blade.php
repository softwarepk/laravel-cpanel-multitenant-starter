@props([
    'title',
    'description' => null,
    'compact' => false,
])

<div {{ $attributes->class([
    'ui-empty-state',
    'ui-empty-state-compact' => $compact,
]) }}>
    <div class="ui-empty-state-mark" aria-hidden="true">·</div>
    <div class="min-w-0">
        <div class="font-semibold text-zinc-950 dark:text-white">{{ $title }}</div>
        @if ($description)
            <div class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $description }}</div>
        @endif
        @isset($action)
            <div class="mt-4">{{ $action }}</div>
        @endisset
        @isset($actions)
            <div class="mt-4">{{ $actions }}</div>
        @endisset
    </div>
</div>
