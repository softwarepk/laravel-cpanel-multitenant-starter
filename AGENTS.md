# Laravel cPanel Multi-Tenant Starter — Agent Guide

This repository is the database-per-tenant sibling of `softwarepk/laravel-cpanel-starter`. It exists for applications where one Laravel deployment serves multiple independent organizations with strong logical/data isolation.

## Read first

Before editing tenancy-sensitive code, read:

- `docs/TENANCY-ARCHITECTURE.md`
- `docs/CPANEL-OPERATIONS.md`
- `docs/GITHUB-GUARDRAILS.md`
- `docs/UI-DESIGN-SYSTEM.md`

Inspect existing central and tenant patterns before creating new abstractions. The tenancy/control-plane behavior in this repository is intentional and was extracted from a working multi-tenant application.

## Foundation stack

- Laravel 13 / PHP 8.3+
- Blade + Livewire 4
- Flux UI 2 + Livewire Blaze
- Tailwind CSS 4 + Vite 8
- Laravel Fortify authentication for tenant users
- stancl/tenancy 3.10
- Pest 5, Pint, Larastan, Rector, Laravel Boost
- SQLite for zero-setup central/local testing; MySQL/MariaDB for cPanel production

## Tenancy invariants

Do not weaken these without an explicit architectural decision:

- central/landlord data and tenant application data are separate;
- each tenant gets its own database;
- tenant users are tenant-local, not global;
- tenant context is determined from the request host before ordinary tenant app/session behavior;
- tenant-owned migrations live in `database/migrations/tenant`;
- tenant storage uses tenancy-aware filesystem context;
- tenant-originated queued work preserves tenant context;
- central administrators are distinct from tenant users;
- suspension must prevent tenant application access;
- destructive tenant/database/domain removal remains guarded and explicit.

Do not solve tenant isolation by adding `tenant_id` to every business table. The database boundary is the primary isolation boundary.

## Central vs tenant code

Central/control-plane code manages tenants, domains, provisioning, lifecycle, central settings, and audit events. It must not become a second application containing project business workflows.

Project-specific business models, users, policies, settings, uploads, and workflows belong in the tenant application/database.

Before adding a central table, ask: "Would two unrelated tenant organizations reasonably share this row?" If no, it belongs in the tenant database.

## Provisioning

The cPanel implementation can automate tenant database and platform-domain provisioning using scoped cPanel credentials. Keep infrastructure operations behind contracts/services so tests and non-cPanel environments can substitute fakes.

Never embed real cPanel credentials, account names, production domains, or database secrets in source code, tests, screenshots, or documentation.

Do not make database/domain deletion an automatic consequence of deleting an Eloquent record. Infrastructure teardown must remain deliberate.

## Authentication

Central administrators and tenant users are separate identities and authentication surfaces. Do not introduce a shared global user table merely for convenience.

Public tenant registration and email verification remain environment/config driven. Central administrator creation is an operational action (`php artisan central:admin`).

## UI

Preserve the base starter's UI system. Prefer `x-ui.*` primitives and existing Flux patterns. The Control Center should remain a professional administration surface, not a separate visual product.

## Laravel conventions

- Use Eloquent directly by default; avoid speculative repository layers.
- Use policies/authorization for protected tenant resources.
- Put substantial state transitions and provisioning workflows in focused Actions/Services.
- Use transactions where multiple records must change atomically.
- Prefer named routes and Laravel-native behavior.
- Do not add Redis, Horizon, external auth, billing, subscriptions, or SaaS features without a concrete project requirement.

## Tests

Every tenancy-sensitive change needs tests for the boundary it touches. At minimum consider:

- central host vs tenant host routing;
- central DB vs tenant DB placement;
- user isolation;
- storage isolation;
- tenant suspension;
- custom/platform domains;
- queue tenant context;
- provisioning/deprovisioning adapters;
- inability to access another tenant by guessed IDs/URLs.

Run focused tests first, then:

```bash
composer fix
composer ci:check
npm run build
```

GitHub Actions capacity may be unavailable; local validation is authoritative. Do not claim tests pass until local output confirms it.

## Delivery

Work normally enters `main` through a focused branch and pull request. Preserve a releasable `main`, use squash merge by default, and keep generic starter improvements distinct from project-specific business logic.
