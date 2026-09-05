---
name: fluxui-development
description: "Use for Flux UI work in this Livewire application: forms, buttons, menus, tables, modals, badges, icons, responsive UI, and theming."
license: MIT
metadata:
  author: laravel
---

# Flux UI Development

Use Flux components when available and thin Blade components (`x-ui.*`) for application presentation patterns.

## Components

The free Flux edition provides avatar, badge, brand, breadcrumbs, button, callout, card, checkbox, dropdown, field, heading, icon, input, modal, navbar, otp-input, pagination, profile, progress, radio, select, separator, skeleton, switch, table, text, textarea, toast, and tooltip.

## Icons

Flux uses Heroicons by default. Confirm the exact Heroicon name; never guess or invent names.

For icons not available in Heroicons, use Lucide and commit the generated component:

```bash
php artisan flux:icon crown grip-vertical github
```

Generated icons live under `resources/views/flux/icon/`. Reuse existing custom icons first.

## Forms

Prefer Flux fields/inputs and server-side Livewire validation. Keep labels visible and use `x-ui.form-actions` for substantial form footers.

## Verification

Render the affected page, check responsive behavior and dark/light presentation, and run the relevant tests plus `composer ci:check`.
