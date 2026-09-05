<x-layouts::app :title="__('Dashboard')">
    <x-ui.page>
        <x-ui.page-header
            :title="__('Tenant Dashboard')"
            :description="__('A neutral tenant workspace. Replace this content with the first useful workflow while preserving the shell and tenancy boundaries.')"
            :eyebrow="__('Tenant application')"
        >
            <x-slot:actions>
                <flux:button variant="primary" :href="route('patterns')" wire:navigate>{{ __('View UI patterns') }}</flux:button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="ui-summary-surface grid divide-y divide-zinc-200 dark:divide-zinc-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="ui-summary-stat"><x-ui.metric :label="__('Architecture')" value="Database per tenant" /></div>
            <div class="ui-summary-stat"><x-ui.metric :label="__('UI stack')" value="Livewire + Flux" /></div>
            <div class="ui-summary-stat"><x-ui.metric :label="__('Deployment target')" value="cPanel / Linux" /></div>
        </div>

        <x-ui.section :title="__('Build inside the tenant boundary')" :description="__('Normal application models, authentication, files, and jobs execute in the tenant context selected from the request hostname.')">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="ui-muted-panel"><div class="ui-eyebrow">{{ __('Isolation') }}</div><h3 class="mt-1 font-semibold">{{ __('Treat each tenant as an independent installation') }}</h3><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __('Tenant users and business data belong in tenant migrations. Central models belong only in the landlord database.') }}</p></div>
                <div class="ui-muted-panel"><div class="ui-eyebrow">{{ __('Consistency') }}</div><h3 class="mt-1 font-semibold">{{ __('Reuse the starter patterns') }}</h3><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __('Use the living pattern gallery and x-ui primitives before creating new interface conventions.') }}</p></div>
            </div>
        </x-ui.section>
    </x-ui.page>
</x-layouts::app>
