@props(['name', 'subtitle' => null, 'initials' => null])
<div class="ui-summary-surface overflow-hidden">
    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            @if($initials)<div class="flex size-11 items-center justify-center rounded-xl bg-zinc-100 font-semibold dark:bg-zinc-800">{{ $initials }}</div>@endif
            <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-xl font-semibold">{{ $name }}</h2>@isset($status){{ $status }}@endisset</div>@if($subtitle)<p class="mt-1 text-sm text-zinc-500">{{ $subtitle }}</p>@endif @isset($meta)<div class="ui-page-meta">{{ $meta }}</div>@endisset</div>
        </div>
        @isset($actions)<div class="flex gap-2">{{ $actions }}</div>@endisset
    </div>
    @isset($stats)<div class="grid border-t border-zinc-200 dark:border-zinc-800 sm:grid-cols-3 sm:divide-x sm:divide-zinc-200 dark:sm:divide-zinc-800">{{ $stats }}</div>@endisset
</div>
