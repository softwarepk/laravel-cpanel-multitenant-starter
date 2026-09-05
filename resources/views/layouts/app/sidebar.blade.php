@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head', ['title' => $title])</head>
<body class="min-h-screen bg-zinc-50 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
    @php($user = auth()->user())
    <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
        <flux:sidebar.header class="border-b border-zinc-200/80 pb-3 dark:border-zinc-800">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-semibold" wire:navigate>
                <span class="flex size-9 items-center justify-center rounded-xl bg-zinc-950 text-xs text-white dark:bg-white dark:text-zinc-950">MT</span>
                <span class="truncate">{{ config('app.name') }}</span>
            </a>
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>
        <flux:sidebar.nav class="pt-3">
            <flux:sidebar.group :heading="__('Workspace')" class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:sidebar.item>
                <flux:sidebar.item icon="squares-2x2" :href="route('patterns')" :current="request()->routeIs('patterns')" wire:navigate>{{ __('Patterns') }}</flux:sidebar.item>
            </flux:sidebar.group>
            <flux:sidebar.group :heading="__('Account')" class="mt-4 grid">
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.*') || request()->routeIs('security.*')" wire:navigate>{{ __('Settings') }}</flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>
        <flux:spacer />
        <div class="border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
            <div class="px-2 font-medium">{{ $user?->name }}</div>
            <div class="px-2 text-xs text-zinc-500">{{ $user?->email }}</div>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf <flux:button type="submit" variant="ghost" class="w-full">{{ __('Log out') }}</flux:button></form>
        </div>
    </flux:sidebar>
    <flux:header class="border-b border-zinc-200 bg-white/95 lg:hidden dark:border-zinc-800 dark:bg-zinc-950/95">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <span class="text-sm font-medium">{{ $user?->name }}</span>
    </flux:header>
    {{ $slot }}
    @fluxScripts
</body>
</html>
