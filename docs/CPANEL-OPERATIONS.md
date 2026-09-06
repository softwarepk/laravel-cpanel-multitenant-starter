# cPanel Operations

## Scope

The starter assumes a conventional Laravel deployment on a cPanel/Linux account. It does not require containers, Kubernetes, Redis, Horizon, or a separate application platform.

The production provisioning layer can automate tenant database and domain setup through cPanel APIs. Keep cPanel credentials scoped as narrowly as practical.

This repository remains a starter rather than a complete production operating model. Review `PRODUCTION-CHECKLIST.md` before a derived application goes live.

## Required concepts

Configure these environment values for production:

- central application hostname(s);
- tenant platform root domain;
- tenant platform document root;
- cPanel host/port/user/token;
- optional tenant database prefix;
- tenant database username/password;
- production central database connection values.

The exact keys are defined by `.env.example` and `config/central.php`.

`CPANEL_API_USER` is the cPanel account/API identity. `TENANT_DB_USERNAME` is the MySQL/MariaDB user Laravel uses for tenant connections and the same user to which cPanel provisioning grants privileges. There is intentionally no second independently configured cPanel DB-user setting.

Database sessions and database cache are intentional multi-tenant defaults: tenant resolution occurs before session/cache access, so those records follow the active tenant database. The database queue remains central and carries tenant context in the queued payload. Database queue jobs wait for surrounding transactions to commit.

HTTPS enforcement defaults on in production through `APP_ENV=production`. Do not set `FORCE_HTTPS=false` unless the deployment intentionally terminates/enforces HTTPS elsewhere and the application-level redirect is not desired.

## Platform hostnames

Each tenant gets a permanent platform hostname such as:

```text
acme.tenants.example.com
```

All tenant platform hostnames point at the same Laravel `public` directory. The hostname selects tenant context; it does not select a different code deployment.

When adding a custom domain, the Control Center provisions/verifies the domain and SSL state before it can become the primary tenant URL.

A non-primary custom domain created by the Control Center can also be removed from the tenant screen. The deprovisioner verifies that the cPanel domain still points at the configured application document root before deleting it.

## Tenant databases

Each tenant receives its own MySQL/MariaDB database. The cPanel provisioning service creates/confirms the database and grants the configured `TENANT_DB_USERNAME` access.

Database names are generated from the tenant ID plus a deterministic short hash. The hash prevents distinct valid IDs such as differently hyphenated labels from collapsing onto one normalized database name. The central `database_name` column is unique.

Once a tenant has been assigned a database name, provisioning retries use the persisted identity rather than recalculating it from the current prefix. Changing `CPANEL_TENANT_DB_PREFIX` therefore affects new tenants only.

Do not store tenant database passwords per tenant unless your hosting model explicitly requires separate DB users. The default cPanel pattern is one restricted application DB user with access to the tenant databases provisioned for that application.

## Explicit provisioning paths

Tenant infrastructure is created only through explicit provisioning workflows:

- production-style provisioning through the Control Center / `ProvisionTenant`;
- local SQLite provisioning through `php artisan tenant:local-create`.

Creating a `Tenant` Eloquent record alone does not automatically create or migrate a database. This avoids accidental infrastructure side effects in future application code.

## First production setup

Typical sequence:

```bash
git clone <repository>
cd <application>
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan starter:install
php artisan migrate --force
npm ci
npm run build
php artisan central:admin
php artisan optimize
```

Then configure the central hostname/document root in cPanel and confirm that the Control Center is reachable over HTTPS.

## Creating a tenant

Use the Control Center. The provisioning action will:

1. reserve the tenant identity and stable database name;
2. create/confirm the tenant's permanent platform hostname;
3. create/confirm the tenant database;
4. run tenant migrations;
5. create the first tenant administrator;
6. verify trusted HTTPS;
7. activate the tenant when ready.

If HTTPS is not ready yet, the tenant stays in an HTTPS-pending provisioning state. Recheck after AutoSSL/certificate issuance completes.

Provisioning remains synchronous by default to keep the starter simple. A derived application may queue `ProvisionTenant` if its hosting limits make long provisioning requests impractical.

## Transient cPanel behavior

The cPanel HTTP client retries a small number of transient connection, rate-limit, and server failures. Domain provisioning/deprovisioning also allows a short period for cPanel's domain metadata to become consistent after a successful mutation.

Logical cPanel errors are not hidden or retried indefinitely. The Control Center records provisioning failures and allows deliberate retry.

The provisioning-readiness check confirms that required configuration values are populated. It does not prove that the token has every required permission or that the host's AutoSSL/domain/database behavior matches expectations. That must be verified during real staging.

## Custom-domain API compatibility

Some addon-domain operations still require cPanel API 2 because cPanel does not currently expose equivalent UAPI operations. API 2 is deprecated by cPanel, so these calls remain isolated behind application contracts/services.

Re-check compatibility when upgrading cPanel or moving the application to a different hosting provider.

## Deploying application updates

Typical deployment:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan tenants:migrate --force
npm ci
npm run build
php artisan optimize
```

Run tenant migrations on every deployment that changes tenant schema. A successful central migration alone does not update tenant databases.

## Queue workers

`QUEUE_CONNECTION=database` is the default. Queue rows live in the central database, while tenant-aware jobs carry the originating tenant ID and restore tenant context when processed.

If cPanel provides a managed long-running process, run a normal Laravel `queue:work` worker and restart it after code/config deployments. On hosts without a persistent worker facility, a cron entry may run:

```bash
php artisan queue:work --stop-when-empty --tries=3
```

If a particular application deliberately uses only synchronous jobs, it may set `QUEUE_CONNECTION=sync`, but that is an application-specific override rather than the multi-tenant starter default.

## Backups

Because each tenant has its own database, backup procedures must cover:

- central database;
- every tenant database;
- tenant storage roots;
- application `.env`/operational secrets through a secure secrets backup process.

Do not assume backing up only the central database protects tenant business data.

## Suspension

Suspension is a logical/control-plane operation. It blocks tenant application access without destroying database/files/domain records.

Use suspension when access must be stopped temporarily. Do not delete a tenant merely to disable it.

## Permanent deletion

Tenant deletion is deliberately guarded and requires the tenant to be suspended first, exact tenant-ID confirmation, and the current central administrator password.

The cleanup action attempts to remove:

- the permanent platform domain;
- custom domains created/managed by the Control Center;
- tenant storage;
- the tenant database;
- the central tenant record.

External infrastructure does not form one transaction. A cPanel/filesystem/database cleanup failure therefore does not automatically block central tenant deletion. Instead the central database retains a `tenant_deletion_records` snapshot containing the original tenant/database/domain identifiers and each cleanup result/error. The Control Center displays this under **Central Activity** for manual follow-up.

If the central tenant record itself cannot be deleted, the operation reports failure rather than claiming that deletion completed.

Before permanent deletion:

- confirm the tenant identity;
- confirm retention/legal requirements;
- take a final backup if required;
- understand whether custom DNS records are controlled externally;
- verify that the cPanel token has only the permissions needed for deprovisioning.

Never attach destructive database/domain deletion directly to an Eloquent `deleted` event.

## Credentials

Never commit:

- cPanel API tokens;
- production DB credentials;
- private customer domains that are not meant to be public;
- SMTP passwords;
- copied `.env` files.

Use `.env` for deployment-specific credentials and rotate cPanel tokens if they are ever exposed.

For production administration, prefer interactive password entry to CLI `--password` options so secrets do not appear in shell history or process listings.

## Local development

Normal automated tests do not call a real cPanel account. A local developer should be able to exercise central/tenant routing and database isolation using SQLite without provisioning public DNS or SSL certificates.

The local creation command remains intentionally explicit:

```bash
php artisan tenant:local-create
```

It creates the SQLite file, central tenant/domain records, runs tenant migrations, and creates the initial tenant administrator without invoking cPanel.
