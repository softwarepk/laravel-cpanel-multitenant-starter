@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-medium text-green-700 dark:text-green-300']) }}>
        {{ $status }}
    </div>
@endif
