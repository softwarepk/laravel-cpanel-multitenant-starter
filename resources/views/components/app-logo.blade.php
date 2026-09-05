@props(['sidebar' => false])

@php
    $configuredName = trim((string) config('app.name'));
    $productName = $configuredName === '' || strcasecmp($configuredName, 'Laravel') === 0
        ? __('Laravel cPanel Multi-Tenant Starter')
        : $configuredName;
    $mark = mb_strtoupper(mb_substr($productName, 0, 1));
@endphp

@if ($sidebar)
    <flux:sidebar.brand :name="$productName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-lg bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-950">
            {{ $mark }}
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$productName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-lg bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-950">
            {{ $mark }}
        </x-slot>
    </flux:brand>
@endif
