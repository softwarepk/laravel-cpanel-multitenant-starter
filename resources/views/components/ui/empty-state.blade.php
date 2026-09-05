@props(['title', 'description' => null])
<div {{ $attributes->class('ui-empty-state') }}>
    <div class="mx-auto flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800">—</div>
    <h3 class="mt-3 font-semibold">{{ $title }}</h3>
    @if($description)<p class="mt-1 text-sm leading-6 text-zinc-500">{{ $description }}</p>@endif
    @isset($actions)<div class="mt-4 flex justify-center gap-2">{{ $actions }}</div>@endisset
</div>
