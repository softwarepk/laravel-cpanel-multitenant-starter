<x-layouts::app :title="__('UI Patterns')">
    <x-ui.page width="wide">
        <x-ui.breadcrumbs :items="[['label' => __('Dashboard'), 'href' => route('dashboard')], ['label' => __('UI Patterns')]]" />
        <x-ui.page-header :title="__('UI Pattern Gallery')" :description="__('Living references for tables, forms, statuses, records, settings, tabs, and empty states.')" :eyebrow="__('Living reference')" />

        <x-ui.section :title="__('Summary surface')" :description="__('Use compact metrics when several values matter together.')">
            <div class="ui-summary-surface grid divide-y divide-zinc-200 dark:divide-zinc-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="ui-summary-stat"><x-ui.metric :label="__('Open')" value="24" emphasis /></div>
                <div class="ui-summary-stat"><x-ui.metric :label="__('In progress')" value="8" emphasis /></div>
                <div class="ui-summary-stat"><x-ui.metric :label="__('Completed')" value="116" emphasis /></div>
            </div>
        </x-ui.section>

        <x-ui.section :title="__('Filters and list table')" :description="__('Keep filtering close to the data and constrain horizontal scrolling to the table shell.')">
            <div class="ui-filter-bar grid gap-3 md:grid-cols-3"><flux:input :label="__('Search')" :placeholder="__('Search records…')" /><flux:select :label="__('Status')"><flux:select.option>{{ __('Active') }}</flux:select.option><flux:select.option>{{ __('All') }}</flux:select.option></flux:select><flux:select :label="__('Page size')"><flux:select.option>20</flux:select.option><flux:select.option>50</flux:select.option><flux:select.option>100</flux:select.option></flux:select></div>
            <x-ui.table-shell><table><thead><tr><th>{{ __('Record') }}</th><th>{{ __('Owner') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead><tbody><tr><td class="font-medium">Example record A</td><td>Alex Morgan</td><td><x-ui.status-badge status="active" /></td><td class="ui-number text-right">12,500</td></tr><tr><td class="font-medium">Example record B</td><td>Sam Lee</td><td><x-ui.status-badge status="pending" /></td><td class="ui-number text-right">8,250</td></tr></tbody></table></x-ui.table-shell>
        </x-ui.section>

        <x-ui.section :title="__('Form pattern')" :description="__('Group related fields and use one consistent action footer.')">
            <form class="ui-settings-block" onsubmit="return false"><div class="ui-settings-block-header"><div class="ui-settings-block-title">{{ __('Example details') }}</div><div class="ui-settings-block-description">{{ __('A substantial form communicates grouping without decorative clutter.') }}</div></div><div class="ui-settings-block-body"><div class="grid gap-5 md:grid-cols-2"><flux:input :label="__('Name')" /><flux:input type="email" :label="__('Email')" /><div class="md:col-span-2"><flux:textarea :label="__('Notes')" rows="3" /></div></div><x-ui.form-actions><flux:button>{{ __('Cancel') }}</flux:button><flux:button variant="primary">{{ __('Save changes') }}</flux:button></x-ui.form-actions></div></form>
        </x-ui.section>

        <x-ui.section :title="__('Record detail')"><x-ui.record-header name="Example Record" subtitle="Reference detail pattern" initials="ER"><x-slot:status><x-ui.status-badge status="active" /></x-slot:status><x-slot:meta><span>REF-00042</span><span>Updated today</span></x-slot:meta><x-slot:stats><div class="ui-summary-stat"><x-ui.metric :label="__('Owner')" value="Alex Morgan" /></div><div class="ui-summary-stat"><x-ui.metric :label="__('Items')" value="12" /></div><div class="ui-summary-stat"><x-ui.metric :label="__('Total')" value="25,550" /></div></x-slot:stats></x-ui.record-header></x-ui.section>

        <x-ui.section :title="__('Tabs and empty state')"><div class="ui-settings-block p-0"><div class="ui-tabs"><button class="ui-tab ui-tab-active">{{ __('Overview') }}</button><button class="ui-tab">{{ __('History') }}</button><button class="ui-tab">{{ __('Documents') }}</button></div><x-ui.empty-state :title="__('No documents yet')" :description="__('Use compact empty states that explain what is missing and what can happen next.')" /></div></x-ui.section>
    </x-ui.page>
</x-layouts::app>
