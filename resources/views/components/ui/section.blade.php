@props(['title' => null, 'description' => null])
<section {{ $attributes->class('ui-section') }}>
    @if($title || $description)
        <div class="ui-section-header">
            @if($title)<h2 class="text-lg font-semibold">{{ $title }}</h2>@endif
            @if($description)<p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $description }}</p>@endif
        </div>
    @endif
    {{ $slot }}
</section>
