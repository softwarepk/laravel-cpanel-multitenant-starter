# UI Design System

This starter uses the same restrained business-application design direction as `laravel-cpanel-starter`: GitHub Primer for action hierarchy/navigation/tables, Atlassian for predictable administrative information architecture, and Linear for density, rhythm, and polish.

## Implementation stack

- Blade + Livewire 4
- Flux UI 2
- Livewire Blaze
- Tailwind CSS 4

Prefer existing `x-ui.*` primitives over one-off markup. Keep central Control Center and tenant application visually related; the Control Center is an administration surface, not a separate product brand.

## Interaction principles

- Put the primary action in a predictable page-header location.
- Keep destructive actions visually secondary until explicitly chosen.
- Use compact status badges for lifecycle states.
- Prefer tables for operational/admin lists.
- Keep forms grouped into clear sections with one consistent action footer.
- Use empty states that explain what to do next.
- Preserve responsive behavior without turning desktop admin pages into oversized mobile cards.
- Avoid decorative gradients, excessive rounded containers, or dashboard chrome that adds no information value.

## Multi-tenant control plane

Tenant administration screens should emphasize operational clarity:

- tenant name/identifier;
- current lifecycle/provisioning status;
- permanent platform domain;
- primary/custom domain state;
- database identity where operationally useful;
- timestamps/error detail for provisioning problems;
- explicit suspend/reactivate/delete actions.

Do not surface secrets such as cPanel tokens or database passwords in the UI.

Provisioning screens should make asynchronous infrastructure state understandable. A tenant waiting for HTTPS should read differently from a failed tenant. Retry actions should be explicit rather than hidden behind ambiguous refresh behavior.

## Icons

Flux uses Heroicons by default. Use exact supported icon names; do not invent names. If a required icon is unavailable, generate a Lucide Flux icon with:

```bash
php artisan flux:icon <name>
```

and commit the generated Blade component.

## Reusable components

Generic reusable components live under:

```text
resources/views/components/ui/
```

Use these for page shells, sections, page/record headers, metrics, status badges, empty states, tables, pagination/page-size controls, breadcrumbs, and form actions.

The `/patterns` tenant route is the living reference for application UI conventions. When introducing a new generic pattern, update the gallery rather than allowing undocumented variants to accumulate.
