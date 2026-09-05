@props([
    'width' => 'standard',
])

@php
    $maxWidth = match ($width) {
        'form', '6xl' => 'max-w-6xl',
        'detail' => 'max-w-[76rem]',
        'wide' => 'max-w-[92rem]',
        'data' => 'max-w-[104rem]',
        '5xl' => 'max-w-5xl',
        '7xl' => 'max-w-7xl',
        default => 'max-w-[84rem]',
    };
@endphp

<div {{ $attributes->class(["ui-page mx-auto flex w-full {$maxWidth} flex-col"]) }}>
    {{ $slot }}
</div>
