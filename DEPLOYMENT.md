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

The installer is a guided wizard covering environment checks, application/domain settings, a live cPanel preflight, central/tenant database configuration, queue-worker setup, the first Control Center administrator, and a final non-secret review.

On final submission the installer verifies that the cPanel API token manages the current hostname, confirms the domain document root, verifies the tenant platform root, constrains database connections to localhost or the MySQL/MariaDB host reported by cPanel, creates/verifies the central database and database users, writes `.env` atomically, runs central migrations, creates the first central administrator, attempts to install/verify the queue-worker cron, and locks the installer.

The installer intentionally does not require normal Laravel sessions or CSRF state because neither may exist yet. It requires HTTPS and successful cPanel-account proof before it writes deployment secrets or configuration. Wizard navigation remains client-side so intermediate secrets do not need to be persisted before an application key/session exists.

Run the frontend build either before or immediately after the installer. The installer itself is self-contained and does not depend on Vite assets:

```bash
npm ci
npm run build
```

## Queue worker

Tenant provisioning and permanent deletion are background operations. A queue worker is therefore required before using the Control Center to create or delete tenants. The starter uses the central database queue by default.

On shared cPanel hosting, the web installer attempts to configure a once-per-minute cron-driven worker automatically. It detects a compatible PHP CLI binary plus `flock`, checks for an existing deployment-specific managed cron entry, adds one only when missing, and verifies it after creation. The generated worker is equivalent to:

```bash
/path/to/flock -n /path/to/storage/framework/queue-worker.lock \
  /path/to/php /path/to/artisan queue:work database \
  --queue=default --stop-when-empty --tries=1 --timeout=600
```

`DB_QUEUE_RETRY_AFTER=900` remains longer than the 600-second tenant-operation timeout. `flock` prevents cron from starting overlapping workers for the same deployment.

cPanel Cron management currently has no UAPI equivalent and therefore uses isolated cPanel API 2 calls. If the hosting account does not permit that API, or the installer cannot safely detect PHP CLI/`flock`, installation still completes but the completion screen reports **Queue worker requires one manual action** and provides the applicable command when available. Resolve that action before tenant creation/deletion.

For a VPS or host with a supervised process manager, a persistent Laravel `queue:work` process is also valid and should be restarted after deployments.

After installation and queue-worker setup, continue at `/central/login` and provision a disposable tenant to validate real cPanel subdomain, database, queue, HTTPS and isolation behavior.

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

The starter defaults to database-backed sessions/cache and a central database queue. Restart long-running queue workers after deployments. Cron-driven `--stop-when-empty` workers start fresh on each invocation and do not need a deploy-time restart.

Production HTTPS enforcement defaults on when `APP_ENV=production`; only set `FORCE_HTTPS=false` when there is an intentional deployment-specific reason.

Never commit production `.env` files, cPanel API tokens, or database/SMTP credentials.
