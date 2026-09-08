# Laravel cPanel Multi-Tenant Starter — Agent Guide

This repository is the database-per-tenant sibling of `softwarepk/laravel-cpanel-starter`. It exists for applications where one Laravel deployment serves multiple independent organizations with strong logical/data isolation.

It is a starter rather than a finished production SaaS product. Preserve simple defaults where appropriate, but do not introduce structural shortcuts that can weaken tenant identity or isolation. Production-specific choices should be configurable or documented when they do not belong in every derived application.

## Read first

Before editing tenancy-sensitive code, read:

- `docs/TENANCY-ARCHITECTURE.md`
- `docs/CPANEL-OPERATIONS.md`
- `docs/PRODUCTION-CHECKLIST.md`
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
- tenant database identity is unique and stable once assigned;
- tenant users are tenant-local, not global;
- tenant context is determined from the request host before ordinary tenant app/session behavior;
- tenant-owned migrations live in `database/migrations/tenant`;
- tenant storage uses tenancy-aware filesystem context;
- tenant-originated queued work preserves tenant context when a derived application opts into queues;
- central administrators are distinct from tenant users;
- suspension must prevent tenant application access;
- provisioning and destructive tenant/database/domain removal remain guarded and explicit;
- deleted tenant/database identities may be reused only after the latest deletion completed cleanly;
- domain/configuration mutations require completed provisioning and no unresolved deletion cleanup.

Do not solve tenant isolation by adding `tenant_id` to every business table. The database boundary is the primary isolation boundary.

## Central vs tenant code

Central/control-plane code manages tenants, domains, provisioning, lifecycle, central settings, deletion history, and audit events. It must not become a second application containing project business workflows.

Project-specific business models, users, policies, settings, uploads, and workflows belong in the tenant application/database.

Before adding a central table, ask: "Would two unrelated tenant organizations reasonably share this row?" If no, it belongs in the tenant database.

## Provisioning

The cPanel implementation automates tenant database and domain provisioning using scoped cPanel credentials. Keep infrastructure operations behind contracts/services so hosting-specific behavior remains isolated and can be replaced when necessary.

Provisioning is explicit. `ProvisionTenant` is the production-style path and `php artisan tenant:local-create` is the local SQLite path. Do not restore implicit infrastructure provisioning to a generic `TenantCreated` Eloquent/package event.

The starter runs tenant creation and permanent deletion synchronously by default and therefore requires no queue worker, cron job, Supervisor, Horizon, or similar background-process infrastructure. A derived application may deliberately introduce queues later when its own workload or hosting limits justify that choice; do not make asynchronous lifecycle processing a starter prerequisite.

Once a database name is assigned to a tenant, retries must use that persisted identity rather than recalculating it from current naming configuration.

A tenant/database identity may be reused only after the latest deletion completed cleanly. Failed, incomplete, or warning-bearing cleanup must continue to block reuse so residual infrastructure cannot be attached to a later tenant generation.

Never embed real cPanel credentials, account names, production domains, or database secrets in source code, tests, screenshots, or documentation.

Do not make database/domain deletion an automatic consequence of deleting an Eloquent record. Infrastructure teardown must remain deliberate. Best-effort external cleanup should retain enough central history for manual follow-up when a hosting operation fails.

## Authentication

Central administrators and tenant users are separate identities and authentication surfaces. Do not introduce a shared global user table merely for convenience.

Public tenant registration and email verification remain environment/config driven. Central administrator creation is an operational action (`php artisan central:admin`).

The starter password policy is intentionally simple by default and centrally configurable. All password creation/reset/change paths should use the same configured Laravel `Password::default()` rule rather than inventing separate validation rules.

MFA is an optional production hardening choice for derived applications, not a mandatory starter dependency.

## UI

Preserve the base starter's UI system. Prefer `x-ui.*` primitives and existing Flux patterns. The Control Center should remain a professional administration surface, not a separate visual product.

Control Center visibility rules are not authorization/lifecycle enforcement by themselves. If the UI hides a tenant mutation while provisioning or deletion cleanup is unresolved, the corresponding backend action must enforce the same lifecycle boundary.

When a cleanly deleted tenant ID is reused, tenant-detail views should show only the current tenant generation's operational activity. Global Central Activity should retain the complete historical audit/deletion record.

## Laravel conventions

- Use Eloquent directly by default; avoid speculative repository layers.
- Use policies/authorization for protected tenant resources.
- Put substantial state transitions and provisioning workflows in focused Actions/Services.
- Use transactions where multiple database records must change atomically.
- Do not pretend cPanel/filesystem/MySQL external operations form one atomic transaction; record partial outcomes where necessary.
- Prefer named routes and Laravel-native behavior.
- Do not add Redis, Horizon, external auth, billing, subscriptions, MFA, or other SaaS features without a concrete project requirement.

## Tests

Every tenancy-sensitive change needs focused tests for the boundary it touches. At minimum consider:

- central host vs tenant host routing;
- central DB vs tenant DB placement;
- database-name uniqueness/stability;
- user isolation;
- storage isolation;
- tenant suspension;
- custom/platform domain ownership and lifecycle mutation guards;
- optional queue tenant context;
- explicit provisioning boundaries;
- clean-only tenant identity reuse and current-generation activity/progress scoping;
- inability to access another tenant by guessed IDs/URLs.

Do not build elaborate infrastructure simulations merely for completeness. Real cPanel behavior must ultimately be validated on a staging account; automated tests should concentrate on deterministic application logic and isolation boundaries.

Run focused tests first, then:

```bash
composer fix
composer ci:check
npm run build
```

GitHub Actions capacity may be unavailable; local validation is authoritative. Do not claim tests pass until local output confirms it.

## Delivery

Work normally enters `main` through a focused branch and pull request. Preserve a releasable `main`, use squash merge by default, and keep generic starter improvements distinct from project-specific business logic.
