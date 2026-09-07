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
- tenant-originated queued work preserves tenant context;
- central administrators are distinct from tenant users;
- suspension must prevent tenant application access;
- provisioning and destructive tenant/database/domain removal remain guarded and explicit;
- tenant configuration mutations must be guarded server-side by lifecycle state, not only hidden in the UI;
- stale lifecycle work must not regain authority after a newer retry/operation takes ownership.

Do not solve tenant isolation by adding `tenant_id` to every business table. The database boundary is the primary isolation boundary.

## Central vs tenant code

Central/control-plane code manages tenants, domains, provisioning, lifecycle, central settings, deletion history, and audit events. It must not become a second application containing project business workflows.

Project-specific business models, users, policies, settings, uploads, and workflows belong in the tenant application/database.

Before adding a central table, ask: "Would two unrelated tenant organizations reasonably share this row?" If no, it belongs in the tenant database.

## Provisioning and queue lifecycle

The cPanel implementation automates tenant database and domain provisioning using scoped cPanel credentials. Keep infrastructure operations behind contracts/services so hosting-specific behavior remains isolated and can be replaced when necessary.

Provisioning is explicit. Production-style tenant creation is reserved by the Control Center and completed by a central queued `ProvisionTenantJob`; `php artisan tenant:local-create` is the explicit local SQLite path. Do not restore implicit infrastructure provisioning to a generic `TenantCreated` Eloquent/package event.

Production tenant creation and permanent deletion require a queue worker. The tenant-operation jobs have a 600-second timeout, the database queue reservation defaults to 900 seconds, and stale-operation recovery intentionally waits longer again. Do not shorten stale detection to the worker timeout boundary without redesigning generation/race protection.

Provisioning retries use a generation/operation ID. Long-running provisioning must revalidate that generation between major stages so an older job cannot continue after a newer retry takes ownership.

Deletion uses a durable `TenantDeletionRecord`. A running deletion must revalidate that record before entering later destructive stages. Stale recovery must retire the previous deletion record before creating a new attempt.

Once a database name is assigned to a tenant, retries must use that persisted identity rather than recalculating it from current naming configuration.

Deletion history blocks tenant/database identity reuse while cleanup is unresolved, failed, or warning-bearing. Reuse is allowed only after the latest matching deletion completed cleanly with no failed cleanup results.

Never embed real cPanel credentials, account names, production domains, or database secrets in source code, tests, screenshots, or documentation.

Do not make database/domain deletion an automatic consequence of deleting an Eloquent record. Infrastructure teardown must remain deliberate. Best-effort external cleanup should retain enough central history for manual follow-up when a hosting operation fails.

## Lifecycle guards

Treat lifecycle state as a backend authorization/safety boundary, not merely UI presentation.

- Tenant application hosts require an active tenant/domain pair.
- Suspended tenants stay suspended until the explicit reactivation action runs.
- HTTPS verification may activate a tenant only while provisioning is specifically waiting for HTTPS; it must not reactivate suspended, failed, or deleting tenants.
- Domain/configuration mutations are unavailable during provisioning, deletion, or unresolved deletion cleanup.
- UI controls should mirror these rules, but direct requests must still be rejected server-side.

## Authentication

Central administrators and tenant users are separate identities and authentication surfaces. Do not introduce a shared global user table merely for convenience.

Public tenant registration and email verification remain environment/config driven. Central administrator creation is an operational action (`php artisan central:admin`).

The starter password policy is intentionally simple by default and centrally configurable. All password creation/reset/change paths should use the same configured Laravel `Password::default()` rule rather than inventing separate validation rules.

MFA is an optional production hardening choice for derived applications, not a mandatory starter dependency.

## UI

Preserve the base starter's UI system. Prefer `x-ui.*` primitives and existing Flux patterns. The Control Center should remain a professional administration surface, not a separate visual product.

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
- tenant suspension/deletion lifecycle;
- custom/platform domain ownership and mutation guards;
- queue tenant context and stale operation generations;
- explicit provisioning boundaries;
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
