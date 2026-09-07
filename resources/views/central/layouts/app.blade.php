<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head', ['title' => $title ?? 'Control Center'])</head>
<body class="min-h-screen bg-zinc-50 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
<div class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    <aside class="hidden border-r border-zinc-200 bg-white lg:flex lg:min-h-screen lg:flex-col dark:border-zinc-800 dark:bg-zinc-950">
        <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
            <a href="{{ route('central.home') }}" class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-zinc-950 text-xs font-semibold text-white dark:bg-white dark:text-zinc-950">MT</div>
                <div><div class="text-sm font-semibold tracking-tight">{{ config('app.name') }}</div><div class="mt-0.5 text-xs text-zinc-500">Control Center</div></div>
            </a>
        </div>
        <nav class="flex-1 space-y-6 px-4 py-5">
            <div><div class="px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-400">Overview</div><div class="mt-2 space-y-1">
                <a href="{{ route('central.home') }}" class="{{ request()->routeIs('central.home') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900' }} block rounded-xl px-3 py-2.5 text-sm font-medium">Dashboard</a>
                <a href="{{ route('central.tenants.index') }}" class="{{ request()->routeIs('central.tenants.*') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900' }} block rounded-xl px-3 py-2.5 text-sm font-medium">Tenants</a>
                <a href="{{ route('central.activity.index') }}" class="{{ request()->routeIs('central.activity.*') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900' }} block rounded-xl px-3 py-2.5 text-sm font-medium">Activity</a>
            </div></div>
            <div><div class="px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-400">Administration</div><div class="mt-2"><a href="{{ route('central.settings.index') }}" class="{{ request()->routeIs('central.settings.*') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900' }} block rounded-xl px-3 py-2.5 text-sm font-medium">Settings</a></div></div>
        </nav>
        <div class="border-t border-zinc-200 p-4 dark:border-zinc-800"><div class="rounded-2xl bg-zinc-50 p-3 dark:bg-zinc-900/70"><div class="text-sm font-medium">{{ auth('central')->user()?->name }}</div><div class="mt-0.5 truncate text-xs text-zinc-500">{{ auth('central')->user()?->email }}</div><form method="POST" action="{{ route('central.logout') }}" class="mt-3">@csrf<button class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-900">Sign out</button></form></div></div>
    </aside>
    <div class="min-w-0">
        <header class="sticky top-0 z-20 border-b border-zinc-200/80 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90"><div class="mx-auto flex h-16 max-w-[1600px] items-center justify-between px-4 sm:px-6 lg:px-8"><div><div class="text-sm font-semibold">{{ $pageTitle ?? 'Control Center' }}</div>@isset($pageEyebrow)<div class="hidden text-xs text-zinc-500 sm:block">{{ $pageEyebrow }}</div>@endisset</div><div class="flex gap-2 lg:hidden"><a href="{{ route('central.home') }}" class="text-xs font-medium">Dashboard</a><a href="{{ route('central.tenants.index') }}" class="text-xs font-medium">Tenants</a><a href="{{ route('central.settings.index') }}" class="text-xs font-medium">Settings</a></div></div></header>
        <main class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @if(session('status'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"><div class="font-semibold">Unable to complete the request.</div><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
@fluxScripts
@stack('scripts')
</body>
</html>