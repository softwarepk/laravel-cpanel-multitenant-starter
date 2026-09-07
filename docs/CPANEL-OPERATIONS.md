# cPanel Operations

## Scope

The starter assumes a conventional Laravel deployment on a cPanel/Linux account. It does not require containers, Kubernetes, Redis, Horizon, or a separate application platform.

The production provisioning layer automates tenant database and domain setup through cPanel APIs. Keep cPanel credentials scoped as narrowly as practical.

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

Database sessions and database cache are intentional multi-tenant defaults: tenant resolution occurs before session/cache access, so those records follow the active tenant database. The database queue remains central and carries tenant context in tenant-aware payloads. Database queue jobs wait for surrounding transactions to commit.

HTTPS enforcement defaults on in production through `APP_ENV=production`. Do not set `FORCE_HTTPS=false` unless the deployment intentionally terminates/enforces HTTPS elsewhere and the application-level redirect is not desired.

## Platform hostnames

Each tenant gets a permanent platform hostname such as:

```text
acme.tenants.example.com
```

All tenant platform hostnames point at the same Laravel `public` directory. The hostname selects tenant context; it does not select a different code deployment.

Custom domains are managed from the central Control Center. A verified custom domain may become primary, while the permanent platform hostname remains as a fallback.

A non-primary custom domain created by the Control Center can be removed from the tenant screen. The deprovisioner verifies that the cPanel domain still points at the configured application document root before deleting it.

Tenant configuration/domain mutations are blocked server-side while provisioning is incomplete, deletion is running, or deletion cleanup remains unresolved. UI hiding is only a usability layer and is not relied upon as the lifecycle guard.

## Tenant databases

Each tenant receives its own MySQL/MariaDB database. The cPanel provisioning service creates/confirms the database and grants the configured `TENANT_DB_USERNAME` access.

Database names are generated from the tenant ID plus a deterministic short hash. The hash prevents distinct valid IDs such as differently hyphenated labels from collapsing onto one normalized database name. The central `database_name` column is unique.

Once a tenant has been assigned a database name, provisioning retries use the persisted identity rather than recalculating it from the current prefix. Changing `CPANEL_TENANT_DB_PREFIX` therefore affects new tenants only.

Deletion history controls reuse. The tenant/database identity remains reserved while the latest matching deletion is started, failed, completed with warnings, or contains failed cleanup results. Reuse is allowed only after a clean completed deletion.

Do not store tenant database passwords per tenant unless your hosting model explicitly requires separate DB users. The default cPanel pattern is one restricted application DB user with access to the tenant databases provisioned for that application.

## Explicit provisioning paths

Tenant infrastructure is created only through explicit provisioning workflows:

- production-style provisioning initiated through the Control Center and completed by the central queue;
- local SQLite provisioning through `php artisan tenant:local-create`.

Creating a `Tenant` Eloquent record alone does not automatically create or migrate a database. This avoids accidental infrastructure side effects in future application code.

## First production setup

The preferred cPanel first-run flow uses the built-in web installer. It deliberately works before `.env`, `APP_KEY`, the central database, or database-backed sessions exist.

Create the application domain in cPanel, clone the repository into that domain's directory, and install PHP dependencies:

```bash
cd /home/<cpanel-user>/<application-domain>
git clone <repository> .
composer install --no-dev --optimize-autoloader --no-interaction
```

Point the cPanel domain document root at:

```text
/home/<cpanel-user>/<application-domain>/public
```

Then open the application over HTTPS. A fresh production clone redirects to `/install`.

The first-run page discovers and pre-fills the hostname, Laravel paths, likely cPanel account/server values, PHP/extensions, writability, document-root state, and conventional database names. The operator supplies the cPanel API token, confirms database credentials/naming, and creates the first Control Center administrator.

On submission the installer verifies that the cPanel account manages the current hostname and tenant platform root, confirms the document root, constrains database connections to localhost or the MySQL/MariaDB host reported by cPanel, creates/verifies the central database and database users, writes the production `.env`, runs central migrations, creates the first central administrator, and locks itself.

The installer is resumable if an external cPanel/database/filesystem step fails. Existing resources are verified and reused where safe rather than pretending cPanel, MySQL, and the filesystem form one transaction.

The installer UI is self-contained and does not depend on Vite assets. Build the normal application assets before or immediately after first-run configuration:

```bash
npm ci
npm run build
```

Before creating or permanently deleting tenants, configure the queue worker described below.

See `WEB-INSTALLER.md` for the detailed installer trust model, discovered fields, recovery behavior, and completion lock.

The older `php artisan starter:install` command remains useful for local/developer CLI setup; it is not the preferred production first-run path.

## Creating a tenant

Use the Control Center. The browser request validates and reserves the tenant identity, assigns a provisioning-operation generation ID, encrypts the temporary tenant-admin password for the queue payload, and dispatches background provisioning.

The queued provisioning action will:

1. confirm the reserved tenant identity and stable database name;
2. create/confirm the tenant's permanent platform hostname;
3. create/confirm the tenant database;
4. run tenant migrations;
5. create the first tenant administrator;
6. verify trusted HTTPS;
7. activate the tenant when ready.

If HTTPS is not ready yet, the tenant stays in an HTTPS-pending provisioning state. The Control Center can recheck HTTPS later.

Provisioning progress is stored centrally, so navigating away or closing the browser does not stop the operation. The running job revalidates its operation-generation ID between major stages so an older stale job cannot continue after a newer retry takes ownership.

## Tenant operation timing and stale recovery

Tenant provisioning/deletion jobs have a 600-second timeout. The database queue `retry_after` default is 900 seconds, so another worker cannot reserve the same job before the first worker's timeout boundary.

Stale-operation recovery deliberately waits longer again (20 minutes by default in the controller logic). This prevents a status poll from declaring a legitimate worker stale at the same moment the worker is reaching its timeout/reservation boundary.

When a provisioning operation is retired as stale, its operation-generation ID is cleared before a retry obtains a new one. When a deletion attempt is retired as stale, its durable deletion record is first marked failed before a new deletion record can be created. Running jobs revalidate ownership between later stages and stop when their operation is no longer current.

## Transient cPanel behavior

The cPanel HTTP client automatically retries only known safe read/check calls when transient connection, rate-limit, or server failures occur. Mutation calls are submitted once so a lost response cannot cause the client to unknowingly repeat a database/domain change that may already have succeeded.

Domain provisioning/deprovisioning also allows a short period for cPanel's domain metadata to become consistent after a successful mutation.

Logical cPanel errors are not hidden or retried indefinitely. The Control Center records provisioning failures and allows deliberate retry.

The provisioning-readiness check confirms that required configuration values are populated. It does not prove that the token has every required permission or that the host's AutoSSL/domain/database behavior matches expectations. That must be verified during real staging.

## Custom-domain API compatibility

Some addon-domain operations still require cPanel API 2 because cPanel does not currently expose equivalent UAPI operations. API 2 is deprecated by cPanel, so these calls remain isolated behind application contracts/services.

Re-check compatibility when upgrading cPanel or moving the application to a different hosting provider.

## Deploying application updates

Typical deployment:

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan tenants:migrate --force
npm ci
npm run build
php artisan optimize
```

Run tenant migrations on every deployment that changes tenant schema. A successful central migration alone does not update tenant databases.

Restart long-running queue workers after each deployment so they do not continue executing old code.

## Queue workers

`QUEUE_CONNECTION=database` is the production default and is required by the built-in tenant provisioning/permanent-deletion workflows. Queue rows live in the central database. Tenant-aware application jobs carry the originating tenant ID and restore tenant context when processed.

If cPanel provides a managed long-running process, run a normal Laravel queue worker and restart it after deployments. On hosts without a persistent worker facility, a cron entry may run:

```bash
php artisan queue:work --queue=default --stop-when-empty --tries=1 --timeout=600
```

When `flock` is available, use it to prevent cron from launching overlapping workers for the same application.

Do not switch production to `QUEUE_CONNECTION=sync` unless you deliberately redesign the built-in tenant lifecycle execution model as well.

## Backups

Because each tenant has its own database, backup procedures must cover:

- central database;
- every tenant database;
- tenant storage roots;
- application `.env`/operational secrets through a secure secrets backup process.

Do not assume backing up only the central database protects tenant business data.

## Suspension and reactivation

Suspension is a logical/control-plane operation. It blocks tenant application access without destroying database/files/domain records.

Use suspension when access must be stopped temporarily. Do not delete a tenant merely to disable it.

HTTPS verification is not a reactivation path. A suspended tenant remains suspended even if the Control Center checks or re-verifies its platform certificate. Reactivation must use the explicit tenant lifecycle action and remains blocked while deletion cleanup is unresolved.

## Permanent deletion

Operational tenant deletion is deliberately guarded and requires the tenant to be suspended first, exact tenant-ID confirmation, and the current central administrator password. A tenant whose provisioning failed may be permanently cleaned up without an additional suspension step.

The browser request creates a durable deletion snapshot and queues the cleanup. The job attempts to remove:

- the permanent platform domain;
- custom domains created/managed by the Control Center;
- tenant storage;
- the tenant database;
- the central tenant record.

Progress is persisted after each external stage. The job revalidates that its deletion record is still the current active attempt before beginning subsequent destructive stages.

External infrastructure does not form one transaction. A cPanel/filesystem/database cleanup failure therefore does not automatically block central tenant deletion. Instead the central database retains a `tenant_deletion_records` snapshot containing the original tenant/database/domain identifiers and each cleanup result/error. The Control Center displays this under **Central Activity** for manual follow-up.

If the central tenant record itself cannot be deleted, the operation reports failure rather than claiming that deletion completed.

A clean completed deletion permits deliberate reuse of the tenant ID/database identity. Any unresolved, failed, or warning-bearing cleanup continues to reserve that identity.

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

It creates the SQLite file, central tenant/domain records, runs tenant migrations, and creates the initial tenant administrator without invoking cPanel or the production queue workflow.
