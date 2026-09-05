<div {{ $attributes->class('ui-form-actions') }}>
    @isset($secondary)<div class="mr-auto flex flex-wrap gap-2">{{ $secondary }}</div>@endisset
    {{ $slot }}
</div>
