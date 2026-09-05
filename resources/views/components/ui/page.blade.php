@props(['width' => 'default'])
@php($max = $width === 'wide' ? 'max-w-[1600px]' : 'max-w-7xl')
<div {{ $attributes->class(['ui-page mx-auto w-full '.$max]) }}>{{ $slot }}</div>
