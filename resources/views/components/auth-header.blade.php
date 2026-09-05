@props(['title', 'description' => null])

<div class="text-center">
    <h1 class="text-xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $title }}</h1>
    @if ($description)
        <p class="mt-1.5 text-sm leading-5 text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>
