# Tenancy Architecture

## Goal

This starter is for applications where one Laravel deployment serves multiple independent organizations while keeping each organization's users and application data isolated as if they were separate installations.

The default model is **database per tenant**, not a shared schema with `tenant_id` columns.

This is logical/data isolation inside one Laravel runtime. It is not process/server isolation; see the infrastructure isolation section below and `PRODUCTION-CHECKLIST.md`.

## Data planes

### Central / landlord database

The central database contains only platform/control-plane data such as:

- tenants;
- domains;
- central administrators;
- central settings;
- central audit logs;
- tenant deletion cleanup history;
- provisioning/lifecycle metadata;
- central queue records.

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
- tenant-local cache/session tables.

The same primary key can exist independently in different tenant databases without creating a collision.

## Tenant database identity

A tenant's database identity is control-plane data and is treated as an invariant.

Production database names are generated from the tenant ID plus a deterministic short hash. This preserves readable names while preventing distinct valid tenant IDs that normalize similarly from mapping to the same database. The central `tenants.database_name` column is unique.

Once a database name is assigned to a tenant, provisioning retries use the persisted identity. Later changes to `CPANEL_TENANT_DB_PREFIX` apply to new tenants only and do not silently move existing tenants to a different database.

Deletion history protects tenant/database identity reuse. The latest matching deletion record blocks reuse while cleanup is `started`, `failed`, `completed_with_warnings`, or otherwise contains failed cleanup results. Reuse is allowed only after the latest matching deletion completed cleanly, so a new tenant cannot silently inherit residual database/storage infrastructure.

The `TENANT_DB_USERNAME` setting is the database user Laravel uses for tenant connections and the same user to which cPanel provisioning grants access.

## Request lifecycle

Tenant requests are host driven. Conceptually:

```text
request host
    -> determine whether host is central or tenant
    -> resolve tenant by domain/subdomain
    -> require an active tenant/domain pair
    -> initialize tenancy
       - switch DB connection
       - switch filesystem context
       - prepare queue context
    -> start ordinary tenant authentication/session/application behavior
```

The central hostname serves only the Control Center. Tenant application routes must not be reachable as an accidental fallback from the central hostname.

Resolving the host before normal web/session handling is also why database-backed sessions are the default. A central request reads the central `sessions` table; a tenant request reads that tenant's `sessions` table. Do not change session storage to a shared backend without re-verifying this boundary.

## Identity model

Central administrators and tenant users are intentionally separate identities.

A tenant user belongs only to that tenant database. The same email address may exist in multiple tenant databases as unrelated accounts.

Do not create global membership/user tables unless a future product requirement explicitly changes the identity model.

Password complexity is a platform policy stored centrally. The starter defaults to a simple minimum length and lets a derived application enable mixed case, numbers, symbols, or compromised-password rejection without changing the identity architecture.

## Domain model

Each tenant has a permanent platform hostname under the configured tenant platform root, for example:

```text
acme.tenants.example.com
```

Custom domains may also be attached. Domain records carry lifecycle/verification state and one domain is primary.

The platform hostname is retained as an operational fallback even when a custom domain becomes primary.

Custom domains are managed from the central Control Center. A non-primary custom domain can be removed through the Control Center, which verifies the cPanel document root before deleting it.

Domain/configuration mutations are server-side guarded by tenant lifecycle state. Hiding controls in the UI is not considered sufficient protection: configuration is blocked while provisioning is incomplete, deletion is running, or deletion cleanup remains unresolved.

## Explicit provisioning boundary

Creating a `Tenant` Eloquent model is a control-plane database operation only. It does not implicitly create/migrate tenant infrastructure.

There are two explicit provisioning paths in the starter:

1. queued production-style provisioning initiated from the Control Center and executed by `ProvisionTenant`;
2. `tenant:local-create` for local/testing SQLite provisioning.

This is intentional. Future application code should not be able to create a database or run migrations merely because it inserted a tenant model.

## Tenant lifecycle

Production tenant provisioning is an explicit **queued** stateful workflow. The browser request reserves the tenant identity and stable database name, assigns a provisioning-operation generation ID, encrypts the temporary tenant-admin password for the queue payload, and dispatches the background job.

The generic sequence is:

1. reserve tenant metadata and stable database identity centrally;
2. create or confirm the platform hostname;
3. create or confirm the tenant database;
4. run tenant migrations;
5. create the initial tenant administrator;
6. wait for trusted HTTPS;
7. activate the tenant.

Provisioning progress is persisted centrally so browser navigation does not stop the operation. A provisioning generation ID is revalidated during the workflow so an older/stale job cannot continue into later stages after a newer retry takes ownership.

The tenant-operation jobs have a 600-second timeout. The database queue reservation (`retry_after`) must remain comfortably longer; the starter default is 900 seconds. The stale-operation recovery window is intentionally longer again so normal worker timeout/reservation boundaries are not mistaken for abandoned work.

HTTPS verification may activate a tenant only while that tenant is explicitly in `status=provisioning` and `provisioning_status=https_pending`. HTTPS rechecks must never reactivate a suspended, failed, or deleting tenant; ordinary reactivation remains a separate lifecycle action.

Suspension is a reversible control-plane state. Suspended tenants must not be allowed to use the tenant application.

Permanent deletion is destructive infrastructure work and must remain guarded. Operational tenants must first be suspended; tenants whose provisioning failed may enter cleanup directly. Do not wire database/domain destruction directly to Eloquent model deletion events.

## Deletion history and partial cleanup

cPanel, MySQL, the filesystem, and the central database do not form one transaction. The starter therefore does not pretend tenant deletion can be atomically rolled back across all of them.

Permanent deletion is queued. Before dispatch, a durable central `tenant_deletion_records` snapshot preserves:

- tenant ID/name;
- database name;
- platform domain;
- custom domains;
- initiating central administrator.

The deletion job attempts cleanup of the platform domain, platform-managed custom domains, tenant storage, tenant database, and finally the central tenant record. Cleanup results are persisted after each external stage.

A running deletion revalidates that its deletion record is still the current active operation before beginning each later destructive stage. If stale recovery or a newer attempt retires the old record, the older job must stop rather than continue deleting infrastructure.

External cleanup failures are recorded and do not automatically prevent removal of the central tenant record. The completed record stores the result/error for every cleanup step and remains available under Central Activity after the tenant record itself is gone. If deletion of the central tenant record fails, the overall operation is reported as failed.

A clean completed deletion permits deliberate tenant-ID reuse. Failed, warning-bearing, or unresolved deletion history continues to reserve the tenant/database identity.

Derived applications remain responsible for their own backup, retention, and legal-hold policy.

## Filesystem isolation

`stancl/tenancy` applies a tenant-specific filesystem suffix/root for configured disks. Application code should normally use Laravel's ordinary `Storage` APIs; tenant context determines the effective tenant path.

Both the starter's `local` and `public` disks are configured as tenant-aware roots. Tests verify that both roots change when tenant context changes.

Do not rely on developers manually prefixing every upload path with a tenant identifier.

Shared application assets such as Vite build assets remain global/public and are not tenant-suffixed.

A derived application's final browser-facing download/public-file design must still be tested. Directly exposing a shared public path can bypass otherwise-correct storage-root isolation if implemented carelessly.

## Sessions, cache, and queue

Database-backed sessions and cache are the starter defaults. Both follow Laravel's active database connection, so after tenant initialization they use that tenant's local `sessions` and `cache` tables. Central requests remain on the central database.

Queue infrastructure is intentionally different: the database queue remains central. The queue connection is pinned to the configured central connection while `stancl/tenancy` adds the originating tenant key to tenant-aware job payloads and restores tenant context when the worker executes the job. Tenant databases therefore do not need a `jobs` table.

The built-in tenant provisioning and permanent-deletion workflows themselves use this central queue, so a queue worker is required in production. Database queue dispatch uses `after_commit=true`, preventing a central queue row from being retained for tenant work dispatched inside a transaction that later rolls back.

If a project changes session, cache, or queue backends, tenant isolation and the built-in lifecycle jobs must be re-verified for the new backend rather than assuming the same guarantees carry over automatically.

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

Long-running queue workers must be restarted after code/config deployments.

## Infrastructure isolation boundary

This starter provides strong **logical/data isolation** inside a shared Laravel deployment. Tenants have separate databases, users, files, and tenant-aware runtime context.

It does not provide process/server isolation. The default cPanel pattern also uses one application database user with access to the tenant databases provisioned for that application.

If a future regulatory/security requirement says that compromise of the shared PHP runtime or shared database credential must not make another tenant's infrastructure technically reachable, use separate deployments/containers/VMs/accounts/credentials instead.

## Security review checklist

For every new tenant-facing or control-plane feature, test the hostile cases, not only normal navigation:

- guessed record IDs;
- copied URLs from another tenant;
- attachment/download URLs;
- exports/reports;
- queued jobs and stale job generations;
- scheduled commands;
- cache keys;
- custom-domain aliases;
- suspended/deleting/failed tenants;
- central-host access to tenant routes;
- direct public-file paths;
- any code that creates or mutates tenant control-plane records.

The success criterion is simple: knowing another tenant's identifiers must not provide a path to its data, and stale lifecycle work must not regain authority after a newer operation takes ownership.
