# Laravel cPanel Multi-Tenant Starter

A reusable Laravel starter for building strongly isolated multi-tenant applications on conventional cPanel/Linux hosting.

## Purpose

Use this repository when one Laravel deployment must serve multiple organizations while keeping each tenant's users and application data isolated in its own database. The starter combines the reusable application foundation from `laravel-cpanel-starter` with the tenancy/control-plane patterns proven in `medical-reimbursement-multitenant`.

This is a sibling of the single-organization starter, not a replacement for it.

## Architecture

- One shared Laravel codebase and release.
- One central/landlord database for tenant metadata, domains, central administrators, settings, and audit events.
- One database per tenant for tenant users and application data.
- Domain/subdomain-first tenant resolution before normal application/session handling.
- Tenant-aware filesystem and queue context through `stancl/tenancy`.
- Separate central Control Center and tenant application surfaces.
- cPanel-oriented provisioning for tenant databases, platform hostnames, custom domains, and HTTPS readiness.

The central database must never contain project business data. New project tables normally belong in `database/migrations/tenant` unless they are truly platform-wide control-plane data.

## Included control plane

The starter intentionally carries forward the proven administrative decisions instead of redesigning them for each application:

- central administrator authentication;
- tenant listing and detail screens;
- tenant provisioning status and retry flow;
- database-per-tenant provisioning;
- platform subdomain provisioning;
- initial tenant administrator creation;
- HTTPS readiness checks before activation;
- custom-domain registration and verification;
- primary-domain management;
- tenant suspension/reactivation;
- guarded tenant deletion/deprovisioning;
- central settings;
- central audit log.

Project-specific business modules should be added inside the tenant application, not the central control plane.

## Stack

- PHP 8.3+
- Laravel 13
- Livewire 4
- Flux UI 2
- Tailwind CSS 4 / Vite 8
- Laravel Fortify
- stancl/tenancy 3.10
- Pest 5, Pint, Larastan, Rector, Laravel Boost

## Local first run

The central application uses SQLite by default for local development. Production is intended for MySQL/MariaDB on cPanel.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan starter:install
php artisan migrate
npm run build
php artisan central:admin
```

`starter:install` configures the application name, central hostname, tenant platform domain, cPanel document root, and Fortify switches.

Local tenant provisioning through the production cPanel provisioners is not required for normal development or automated tests. Tests use isolated local tenant databases and fake infrastructure adapters where appropriate.

## Production environment

At minimum configure the central database and platform host settings in `.env`. cPanel automation additionally requires the cPanel host/user/token and the tenant database/user conventions expected by `config/central.php`.

Important concepts:

- `CENTRAL_DOMAINS` identifies hostnames that are allowed to serve the Control Center.
- `TENANT_PLATFORM_DOMAIN` is the root under which permanent tenant hostnames are created, such as `tenant.example.com`.
- `TENANT_PLATFORM_DOCUMENT_ROOT` points every tenant platform hostname at the same Laravel `public` directory.
- tenant databases use the central cPanel account's configured tenant DB prefix and tenant DB user.

Do not commit cPanel tokens or real credentials.

## Deployment

A typical cPanel deployment remains conventional:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan tenants:migrate --force
npm ci
npm run build
php artisan optimize
```

Central migrations update the control plane. Tenant migrations update the application schema in every tenant database.

When a new tenant is created through the Control Center, the provisioning workflow creates/confirms the platform hostname and database, applies tenant migrations, creates the initial tenant administrator, and waits for trusted HTTPS before activation.

## Adding project schema

Place tenant-owned schema in:

```text
database/migrations/tenant/
```

Examples include customers, orders, tickets, assets, workflows, settings that belong to one organization, and any application-specific user relationships.

Place a migration in the normal `database/migrations/` directory only when the data is central platform metadata shared by the tenancy control plane.

Avoid adding `tenant_id` columns to ordinary tenant tables. The database boundary is the primary tenant boundary.

## Isolation rules

Treat these as architectural invariants:

1. A tenant request must resolve a tenant before tenant authentication or business data access.
2. Tenant users live in tenant databases, not centrally.
3. Business records live in tenant databases, not centrally.
4. Tenant files must use tenant-aware storage context.
5. Queued work that originates inside a tenant must preserve tenant context.
6. Central routes must never become a back door to tenant business data.
7. A record identifier from Tenant A must never allow access to Tenant B.
8. Destructive deprovisioning must remain explicit and guarded.

## Verification

Run focused tests while developing, then use the same quality gate as the base starter:

```bash
composer fix
composer ci:check
npm run build
```

The test suite must cover both ordinary Laravel behavior and tenant-boundary behavior. See `docs/TENANCY-ARCHITECTURE.md` and `AGENTS.md` before modifying tenancy infrastructure.

## Relationship to the other repositories

- `softwarepk/laravel-cpanel-starter` — lean single-organization foundation.
- `softwarepk/laravel-cpanel-multitenant-starter` — this repository; same foundation plus a database-per-tenant control plane.
- `softwarepk/medical-reimbursement-multitenant` — real application in which these tenancy patterns were proven.

Generic improvements to Laravel, Livewire, Flux, tooling, UI conventions, and cPanel deployment should be periodically compared with the single-tenant starter. Tenancy-specific decisions belong here.
