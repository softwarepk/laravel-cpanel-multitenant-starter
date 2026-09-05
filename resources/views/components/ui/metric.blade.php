@props(['label', 'value', 'emphasis' => false])
<div>
    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
    <div @class(['mt-1 font-semibold tracking-tight', 'text-2xl' => $emphasis, 'text-lg' => ! $emphasis])>{{ $value }}</div>
</div>
