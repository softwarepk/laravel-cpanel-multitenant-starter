@props([
    'sticky' => false,
])

<div {{ $attributes->class([
    'ui-form-actions',
    'ui-form-actions-sticky' => $sticky,
]) }}>
    @isset($secondary)
        <div class="ui-form-actions-secondary">
            {{ $secondary }}
        </div>
    @endisset

    <div class="ui-form-actions-primary">
        {{ $slot }}
    </div>
</div>
