@props(['status'])
@php
    $label = ucfirst(str_replace('_', ' ', (string) $status));
    $classes = match ((string) $status) {
        'active', 'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
        'pending', 'provisioning' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
        'suspended' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
        'failed' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };
@endphp
<span {{ $attributes->class('inline-flex rounded-full px-2.5 py-1 text-xs font-medium '.$classes) }}>{{ $label }}</span>
