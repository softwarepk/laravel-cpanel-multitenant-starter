<x-layouts::app :title="__('Dashboard')">
    <x-ui.page>
        <x-ui.page-header
            :title="__('Starter Dashboard')"
            :description="__('A neutral workspace showing the foundation that should survive when a new product replaces the starter content.')"
            :eyebrow="__('Starter workspace')"
        >
            <x-slot:actions>
                <flux:button variant="primary" icon="layout-grid" :href="route('patterns')" wire:navigate>
                    {{ __('View UI patterns') }}
                </flux:button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="ui-summary-surface grid divide-y divide-zinc-200 dark:divide-zinc-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="ui-summary-stat"><x-ui.metric :label="__('Platform')" value="Laravel 13" /></div>
            <div class="ui-summary-stat"><x-ui.metric :label="__('UI stack')" value="Livewire + Flux" /></div>
            <div class="ui-summary-stat"><x-ui.metric :label="__('Tenancy')" value="Database per tenant" /></div>
        </div>

        <x-ui.section :title="__('Build the first useful workflow')" :description="__('Keep the shell, tenancy boundaries, and conventions; replace the neutral starter content and get a real vertical slice in front of users early.')">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="ui-muted-panel"><div class="ui-eyebrow">{{ __('Consistency') }}</div><h3 class="mt-1 text-base font-semibold">{{ __('Reuse before redesigning') }}</h3><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __('The pattern gallery and x-ui components are the default starting point for lists, forms, records, settings, statuses, and empty states.') }}</p></div>
                <div class="ui-muted-panel"><div class="ui-eyebrow">{{ __('Isolation') }}</div><h3 class="mt-1 text-base font-semibold">{{ __('Keep business data tenant-local') }}</h3><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __('Project business migrations and tenant users belong in tenant databases. The central database remains a control plane only.') }}</p></div>
            </div>
        </x-ui.section>
    </x-ui.page>
</x-layouts::app>
