# Tenancy Architecture

## Goal

This starter is for applications where one Laravel deployment serves multiple independent organizations while keeping each organization's users and application data isolated as if they were separate installations.

The default model is **database per tenant**, not a shared schema with `tenant_id` columns.

## Data planes

### Central / landlord database

The central database contains only platform/control-plane data such as:

- tenants;
- domains;
- central administrators;
- central settings;
- central audit logs;
- provisioning/lifecycle metadata.

It must not contain project business records or tenant users.

### Tenant databases

Every tenant gets its own database. Tenant-owned schema lives under:

```text
database/migrations/tenant/
```

Typical tenant tables include:

- users;
- application settings belonging to one organization;
- customers, assets, tickets, orders, claims, documents, workflows, etc.;
- project-specific authorization/relationship tables;
- tenant-local cache/session tables when configured that way.

The same primary key can exist independently in different tenant databases without creating a collision.

## Request lifecycle

Tenant requests are host driven. Conceptually:

```text
request host
    -> determine whether host is central or tenant
    -> resolve tenant by domain/subdomain
    -> initialize tenancy
       - switch DB connection
       - switch filesystem context
       - prepare queue context
    -> start ordinary tenant authentication/session/application behavior
```

The central hostname serves only the Control Center. Tenant application routes must not be reachable as an accidental fallback from the central hostname.

## Identity model

Central administrators and tenant users are intentionally separate identities.

A tenant user belongs only to that tenant database. The same email address may exist in multiple tenant databases as unrelated accounts.

Do not create global membership/user tables unless a future product requirement explicitly changes the identity model.

## Domain model

Each tenant has a permanent platform hostname under the configured tenant platform root, for example:

```text
acme.tenants.example.com
```

Custom domains may also be attached. Domain records carry lifecycle/verification state and one domain is primary.

The platform hostname is retained as an operational fallback even when a custom domain becomes primary.

## Tenant lifecycle

Provisioning is an explicit stateful workflow. The generic sequence is:

1. create/record tenant metadata centrally;
2. create or confirm the platform hostname;
3. create or confirm the tenant database;
4. run tenant migrations;
5. create the initial tenant administrator;
6. wait for trusted HTTPS;
7. activate the tenant.

Failures are recorded as provisioning failures and may be retried.

Suspension is a reversible control-plane state. Suspended tenants must not be allowed to use the tenant application.

Permanent deletion is destructive infrastructure work and must remain guarded. Do not wire database/domain destruction directly to Eloquent model deletion events.

## Filesystem isolation

`stancl/tenancy` applies a tenant-specific filesystem suffix/root for configured disks. Application code should normally use Laravel's ordinary `Storage` APIs; tenant context determines the effective tenant path.

Do not rely on developers manually prefixing every upload path with a tenant identifier.

Shared application assets such as Vite build assets remain global/public and are not tenant-suffixed.

## Cache and queue

Tenant-originated cache data must not leak to another tenant. Tenant-originated queued work must retain the originating tenant key so workers can restore tenant context before executing application logic.

If a project changes the default cache/queue backend, tenant isolation must be re-verified for that backend.

## Migrations and deployment

Central migrations:

```text
database/migrations/
```

Tenant migrations:

```text
database/migrations/tenant/
```

A deployment normally runs both:

```bash
php artisan migrate --force
php artisan tenants:migrate --force
```

New application tables should default to tenant migrations. Central migrations should be rare and reserved for platform-wide tenancy/control-plane metadata.

## Infrastructure isolation boundary

This starter provides strong **logical/data isolation** inside a shared Laravel deployment. Tenants have separate databases, users, files, and tenant-aware runtime context.

It does not provide process/server isolation. If a future regulatory/security requirement says that compromise of the shared PHP runtime must not make another tenant's infrastructure credentials or database technically reachable, use separate deployments/containers/VMs/credentials instead.

## Security review checklist

For every new tenant-facing feature, test the hostile cases, not only normal navigation:

- guessed record IDs;
- copied URLs from another tenant;
- attachment/download URLs;
- exports/reports;
- queued jobs;
- scheduled commands;
- cache keys;
- custom-domain aliases;
- suspended tenants;
- central-host access to tenant routes.

The success criterion is simple: knowing another tenant's identifiers must not provide a path to its data.
