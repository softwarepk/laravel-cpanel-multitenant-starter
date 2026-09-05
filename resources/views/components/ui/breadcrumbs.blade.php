@props(['items' => []])
<nav class="flex flex-wrap items-center gap-2 text-sm text-zinc-500" aria-label="Breadcrumb">
    @foreach($items as $item)
        @if(!$loop->first)<span>/</span>@endif
        @if(isset($item['href']))<a href="{{ $item['href'] }}" class="hover:text-zinc-950 dark:hover:text-white">{{ $item['label'] }}</a>@else<span>{{ $item['label'] }}</span>@endif
    @endforeach
</nav>
