@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head', ['title' => $title])</head>
<body class="min-h-screen bg-zinc-50 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <div class="w-full max-w-md">
            <a href="/" class="mb-8 flex items-center gap-3 font-semibold">
                <span class="flex size-10 items-center justify-center rounded-xl bg-zinc-950 text-xs text-white dark:bg-white dark:text-zinc-950">MT</span>
                {{ config('app.name') }}
            </a>
            {{ $slot }}
        </div>
    </main>
    @fluxScripts
</body>
</html>
