@props(['title', 'description' => null, 'eyebrow' => null])
<div {{ $attributes->class('ui-page-header flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between') }}>
    <div class="min-w-0">
        @if($eyebrow)<div class="ui-eyebrow">{{ $eyebrow }}</div>@endif
        <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $title }}</h1>
        @if($description)<p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="flex shrink-0 flex-wrap gap-2">{{ $actions }}</div>@endisset
</div>
