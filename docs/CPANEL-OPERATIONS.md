# cPanel Operations

## Scope

The starter assumes a conventional Laravel deployment on a cPanel/Linux account. It does not require containers, Kubernetes, Redis, Horizon, a queue worker, or a separate application platform.

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

Database sessions and database cache are intentional multi-tenant defaults: tenant resolution occurs before session/cache access, so those records follow the active tenant database. The starter defaults to `QUEUE_CONNECTION=sync`; no external queue worker is required for the built-in tenant lifecycle.

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

The installer is a guided flow. It first checks the runtime and paths, then explicitly verifies the cPanel connection and database configuration before the final installation changes resources. Existing database users are never silently assigned a new password; their submitted credentials must authenticate. A new installation will not reuse a non-empty central database unless it is the database recorded for the interrupted installation being resumed.

On final submission the installer verifies the cPanel account again, creates/verifies the central database and database users, writes the production `.env`, runs central migrations, creates the first central administrator, and locks itself.

The installer is resumable if an external cPanel/database/filesystem step fails. Existing resources are verified and reused where safe rather than pretending cPanel, MySQL, and the filesystem form one transaction.

The installer UI itself is self-contained and does not depend on Vite assets. Normal application pages do. Before handoff, confirm that the frontend build exists:

```text
public/build/manifest.json
```

You may build it on cPanel:

```bash
npm ci
npm run build
```

or build elsewhere and deploy the resulting `public/build/` directory. Node.js is not required at runtime once those generated assets are present.

After installation, continue at `/central/login` and provision a disposable tenant for the real cPanel staging exercise.

See `WEB-INSTALLER.md` for the detailed installer trust model, discovered fields, recovery behavior, and completion lock.

The older `php artisan starter:install` command remains useful for local/developer CLI setup; it is not the preferred production first-run path.

## Creating a tenant

Use the Control Center. The provisioning action will:

1. reserve the tenant identity and stable database name;
2. create/confirm the tenant's permanent platform hostname;
3. create/confirm the tenant database;
4. run tenant migrations;
5. create the first tenant administrator;
6. verify trusted HTTPS;
7. activate the tenant when ready.

Provisioning runs synchronously in the Control Center request. Keep the browser page open while the operation is running. The operation records each provisioning stage, and failures leave the tenant in a clear failed state that can be retried without blindly recreating resources.

If HTTPS is not ready yet, the tenant stays in an HTTPS-pending provisioning state. Recheck after AutoSSL/certificate issuance completes.

A derived application with longer-running workloads may deliberately move lifecycle operations to a queue later, but that is an opt-in deployment decision rather than a requirement of this starter.

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
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan tenants:migrate --force
npm ci
npm run build
php artisan optimize
```

Run tenant migrations on every deployment that changes tenant schema. A successful central migration alone does not update tenant databases.

## Optional queues

The starter itself uses `QUEUE_CONNECTION=sync` and requires no worker. Laravel's database and other queue drivers remain available for applications that later choose to introduce asynchronous jobs.

If a derived application opts into an asynchronous queue, that application must also choose an appropriate worker strategy for its hosting environment. Do not assume shared cPanel supports a particular cron frequency or persistent process model.

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

Tenant deletion is deliberately guarded. An active tenant must first be suspended. The operator then supplies the exact tenant ID and the current Control Center administrator password. A tenant whose provisioning failed may also be cleaned up directly because it is not an operational tenant.

The cleanup action attempts to remove:

- the permanent platform domain;
- custom domains created/managed by the Control Center;
- tenant storage;
- the tenant database;
- the central tenant record.

External infrastructure does not form one transaction. A cPanel/filesystem/database cleanup failure therefore does not automatically block central tenant deletion. Instead the central database retains a `tenant_deletion_records` snapshot containing the original tenant/database/domain identifiers and each cleanup result/error. The Control Center displays this under **Central Activity** for manual follow-up.

A tenant ID/database identity may be reused only after the latest deletion completed cleanly. Failed, incomplete, or warning-bearing cleanup continues to block reuse so residual infrastructure cannot be accidentally attached to a later tenant.

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
