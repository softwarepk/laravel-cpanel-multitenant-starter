# Deployment

See `docs/CPANEL-OPERATIONS.md` for the complete multi-tenant cPanel operating model.

## First cPanel deployment

For a new production-style cPanel deployment, do not create `.env` manually first. The starter includes a stateless first-run web installer that can bootstrap the production environment before the central database or session tables exist.

Typical sequence:

```bash
cd /home/<cpanel-user>/<application-domain>
git clone <repository> .
composer install --no-dev --optimize-autoloader --no-interaction
```

Point the cPanel domain document root at the cloned application's `public` directory, for example:

```text
/home/<cpanel-user>/<application-domain>/public
```

Then open the application over HTTPS. An uninstalled production clone redirects to `/install`.

The installer is a six-step guided wizard covering environment checks, application/domain settings, a live cPanel preflight, central/tenant database preflight, the first Control Center administrator, and a final non-secret review.

Before final installation the browser can perform two read-only checks:

1. **cPanel connection** — verifies that the token manages the current installer hostname, that the cPanel document root matches this Laravel `public` directory, that the tenant platform root belongs to the same account, and reports the MySQL/MariaDB host.
2. **Database configuration** — verifies permitted database hosts, checks whether the central database and both DB users already exist, validates submitted passwords for existing users, and refuses a non-empty central database unless it belongs to the recorded interrupted installation being resumed.

The final submission repeats the authoritative checks, creates/verifies the central database and database users, writes `.env` atomically, runs central migrations, creates the first Control Center administrator, and locks the installer.

The installer intentionally does not require normal Laravel sessions or CSRF state because neither may exist yet. It requires HTTPS and successful cPanel-account proof before it writes deployment secrets or configuration. Wizard navigation remains client-side so intermediate secrets do not need to be persisted before an application key/session exists.

## Frontend assets

The installer page itself is self-contained and does not depend on Vite, but the normal Control Center and tenant UI do. A finished deployment therefore requires:

```text
public/build/manifest.json
public/build/assets/...
```

If `public/build/manifest.json` is missing, normal application pages can fail after installation with Laravel's `ViteManifestNotFoundException`.

Assets may be built directly on cPanel:

```bash
npm ci
npm run build
```

or built on a developer/build machine and the resulting `public/build/` directory deployed to cPanel. Node.js is not required at runtime once those generated assets are present.

For now the starter does not enforce or automate either asset-build model. Treat the presence of `public/build/manifest.json` as a deployment-readiness check before handing the application over for use.

## Tenant lifecycle

Tenant creation and permanent deletion are synchronous by default. They execute during the Control Center request and do **not** require a queue worker, cron entry, Supervisor, systemd, or other external scheduler.

This is deliberate for the shared-cPanel starter: the default operating model favors simple deployment and fewer hosting-specific dependencies. Provisioning remains retryable/idempotent where practical, so a failed operation records its state and can be retried from the Control Center rather than requiring a background worker.

Because cPanel domain/database operations can take time, keep the browser request open while a tenant is being provisioned or permanently deleted. Deployments with heavier workloads can opt into Laravel queues later as an application-specific enhancement; the starter does not require them.

After installation, continue at `/central/login` and provision a disposable tenant to validate real cPanel subdomain, database, HTTPS and isolation behavior.

## Application updates

A normal application update runs both central and tenant migrations:

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan tenants:migrate --force
npm ci
npm run build
php artisan optimize
```

The central database contains control-plane metadata. Each tenant database contains that tenant's users and project business data. Back up both the central database and all tenant databases/storage roots.

The starter defaults to database-backed sessions/cache and `QUEUE_CONNECTION=sync`. Laravel's other queue drivers remain available to applications that deliberately choose to add background processing later.

Production HTTPS enforcement defaults on when `APP_ENV=production`; only set `FORCE_HTTPS=false` when there is an intentional deployment-specific reason.

Never commit production `.env` files, cPanel API tokens, or database/SMTP credentials.
