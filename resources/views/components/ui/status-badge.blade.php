@props([
    'status' => null,
    'label' => null,
    'tone' => null,
])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) ($status ?? '');
    $normalized = str($value)->lower()->replace([' ', '-'], '_')->toString();
    $label ??= filled($value) ? __(str($value)->headline()->toString()) : __('Status');

    $tone ??= match ($normalized) {
        'active', 'approved', 'complete', 'completed', 'current', 'finalized', 'paid', 'processed', 'success' => 'success',
        'attention', 'returned', 'reopened', 'warning' => 'warning',
        'danger', 'error', 'failed', 'rejected', 'voided' => 'danger',
        'in_progress', 'open', 'pending', 'processing', 'submitted' => 'info',
        default => 'neutral',
    };

    [$toneClasses, $dotClasses] = match ($tone) {
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/80 dark:bg-emerald-950/45 dark:text-emerald-300', 'bg-emerald-500'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/80 dark:bg-amber-950/45 dark:text-amber-300', 'bg-amber-500'],
        'danger' => ['border-red-200 bg-red-50 text-red-700 dark:border-red-900/80 dark:bg-red-950/45 dark:text-red-300', 'bg-red-500'],
        'info' => ['border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/80 dark:bg-blue-950/50 dark:text-blue-300', 'bg-blue-500'],
        default => ['border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300', 'bg-zinc-400'],
    };
@endphp

<span {{ $attributes->class("inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-semibold {$toneClasses}") }}>
    <span class="size-1.5 rounded-full {{ $dotClasses }}" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</span>
