<x-layouts::app :title="__('UI Patterns')">
    <x-ui.page width="wide">
        <x-ui.breadcrumbs :items="[
            ['label' => __('Dashboard'), 'href' => route('dashboard')],
            ['label' => __('UI Patterns')],
        ]" />

        <x-ui.page-header
            :title="__('UI Pattern Gallery')"
            :description="__('A living reference for the application shell, action hierarchy, tables, forms, statuses, records, settings, tabs, and empty states.')"
            :eyebrow="__('Living reference')"
        >
            <x-slot:actions>
                <flux:button icon="book-open-text">{{ __('Secondary action') }}</flux:button>
                <flux:button variant="primary">{{ __('Primary action') }}</flux:button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.section
            :title="__('Summary surface')"
            :description="__('Use compact metrics when several values matter together; avoid turning every page into a dashboard of decorative cards.')"
        >
            <div class="ui-summary-surface grid divide-y divide-zinc-200 dark:divide-zinc-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="ui-summary-stat"><x-ui.metric :label="__('Open')" value="24" emphasis /></div>
                <div class="ui-summary-stat"><x-ui.metric :label="__('In progress')" value="8" emphasis /></div>
                <div class="ui-summary-stat"><x-ui.metric :label="__('Completed')" value="116" emphasis /></div>
            </div>
        </x-ui.section>

        <x-ui.section
            :title="__('Filters and list table')"
            :description="__('Keep filters close to the data, make the primary task obvious, and constrain horizontal scrolling to the table shell.')"
        >
            <div class="ui-filter-bar grid gap-3 md:grid-cols-[minmax(0,1fr)_13rem_13rem_auto]">
                <flux:input :label="__('Search')" :placeholder="__('Search records…')" />
                <flux:select :label="__('Status')">
                    <flux:select.option>{{ __('Active') }}</flux:select.option>
                    <flux:select.option>{{ __('All') }}</flux:select.option>
                </flux:select>
                <flux:select :label="__('Page size')">
                    <flux:select.option>20</flux:select.option>
                    <flux:select.option>50</flux:select.option>
                    <flux:select.option>100</flux:select.option>
                </flux:select>
                <div class="flex items-end"><flux:button class="w-full">{{ __('Clear') }}</flux:button></div>
            </div>

            <x-ui.table-shell>
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Record') }}</th>
                            <th>{{ __('Owner') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="font-medium">Example record A</td><td>Alex Morgan</td><td><x-ui.status-badge status="active" /></td><td class="ui-number text-right">12,500</td></tr>
                        <tr><td class="font-medium">Example record B</td><td>Sam Lee</td><td><x-ui.status-badge status="pending" /></td><td class="ui-number text-right">8,250</td></tr>
                        <tr><td class="font-medium">Example record C</td><td>Jordan Patel</td><td><x-ui.status-badge status="completed" /></td><td class="ui-number text-right">4,800</td></tr>
                    </tbody>
                </table>
            </x-ui.table-shell>
        </x-ui.section>

        <x-ui.section :title="__('Form pattern')" :description="__('Group related fields, keep labels visible, and use one consistent action footer for secondary and primary actions.')">
            <div class="ui-settings-block">
                <div class="ui-settings-block-header"><div class="ui-settings-block-title">{{ __('Example details') }}</div><div class="ui-settings-block-description">{{ __('A substantial form should communicate grouping without adding decorative card clutter.') }}</div></div>
                <div class="ui-settings-block-body">
                    <div class="grid gap-5 md:grid-cols-2"><flux:input :label="__('Name')" :placeholder="__('Example name')" /><flux:input type="email" :label="__('Email')" placeholder="name@example.com" /><div class="md:col-span-2"><flux:textarea :label="__('Notes')" rows="3" :placeholder="__('Optional notes')" /></div></div>
                    <x-ui.form-actions><x-slot:secondary><flux:button variant="ghost" icon="folder-git-2">{{ __('Utility') }}</flux:button></x-slot:secondary><flux:button>{{ __('Cancel') }}</flux:button><flux:button variant="primary">{{ __('Save changes') }}</flux:button></x-ui.form-actions>
                </div>
            </div>
        </x-ui.section>

        <x-ui.section :title="__('Record detail')" :description="__('Strong identity, status, compact metadata, useful metrics, and predictable actions form the default detail-page header.')">
            <x-ui.record-header name="Example Record" subtitle="Reference detail pattern" initials="ER">
                <x-slot:status><x-ui.status-badge status="active" /></x-slot:status><x-slot:meta><span>REF-00042</span><span>Updated today</span></x-slot:meta><x-slot:actions><flux:button>{{ __('Edit') }}</flux:button></x-slot:actions>
                <x-slot:stats><div class="ui-summary-stat"><x-ui.metric :label="__('Owner')" value="Alex Morgan" /></div><div class="ui-summary-stat"><x-ui.metric :label="__('Items')" value="12" /></div><div class="ui-summary-stat"><x-ui.metric :label="__('Total')" value="25,550" /></div></x-slot:stats>
            </x-ui.record-header>
        </x-ui.section>

        <x-ui.section :title="__('Settings workspace')" :description="__('Use persistent local navigation for related routed settings pages rather than disguising navigation as tabs.')">
            <div class="ui-settings-shell">
                <aside class="ui-settings-sidebar">
                    <div class="ui-settings-sidebar-intro"><div class="ui-settings-sidebar-kicker">{{ __('Administration') }}</div><div class="ui-settings-sidebar-copy">{{ __('Related settings stay oriented around one persistent navigation area.') }}</div></div>
                    <nav class="ui-settings-nav"><div class="ui-settings-nav-group"><div class="ui-settings-nav-label">{{ __('General') }}</div><a href="#" class="ui-settings-nav-link ui-settings-nav-link-active"><span class="ui-settings-nav-title">{{ __('Organization') }}</span><span class="ui-settings-nav-description">{{ __('Name and defaults') }}</span></a><a href="#" class="ui-settings-nav-link"><span class="ui-settings-nav-title">{{ __('Notifications') }}</span><span class="ui-settings-nav-description">{{ __('Delivery preferences') }}</span></a></div></nav>
                </aside>
                <section class="ui-settings-workspace">
                    <div class="ui-settings-workspace-header"><div><div class="ui-settings-context">{{ __('General') }}</div><h3 class="text-xl font-semibold">{{ __('Organization') }}</h3><p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('The selected settings route owns the work surface.') }}</p></div></div>
                    <div class="ui-settings-workspace-body"><div class="ui-settings-summary"><div class="ui-settings-summary-item"><div class="ui-settings-summary-label">{{ __('Environment') }}</div><div class="ui-settings-summary-value">Production</div></div><div class="ui-settings-summary-item"><div class="ui-settings-summary-label">{{ __('Region') }}</div><div class="ui-settings-summary-value">Default</div></div></div></div>
                </section>
            </div>
        </x-ui.section>

        <x-ui.section :title="__('True tabs and empty state')" :description="__('Tabs switch panels inside one context. Empty states explain what is missing and what the user can do next.')">
            <div class="ui-settings-block p-0"><div class="ui-tabs"><button type="button" class="ui-tab ui-tab-active">{{ __('Overview') }}</button><button type="button" class="ui-tab">{{ __('History') }}</button><button type="button" class="ui-tab">{{ __('Documents') }}</button></div><x-ui.empty-state :title="__('No documents yet')" :description="__('Add supporting material when the workflow requires it. Keep ordinary empty states compact and useful.')" /></div>
        </x-ui.section>
    </x-ui.page>
</x-layouts::app>
