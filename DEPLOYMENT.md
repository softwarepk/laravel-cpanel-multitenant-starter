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

The installer discovers and pre-fills the current hostname, Laravel paths, likely cPanel account/server values, PHP/extensions, writability, HTTPS, document-root state, and database naming suggestions. The operator supplies the remaining secrets and the first Control Center administrator.

On submission the installer verifies that the cPanel API token manages the current hostname, confirms the domain document root, verifies the tenant platform root, constrains database connections to localhost or the MySQL/MariaDB host reported by cPanel, creates/verifies the central database and database users, writes `.env` atomically, runs central migrations, creates the first central administrator, and locks the installer.

The installer intentionally does not require normal Laravel sessions or CSRF state because neither may exist yet. It requires HTTPS and successful cPanel-account proof before it writes deployment secrets or configuration.

Run the frontend build either before or immediately after the installer. The installer itself is self-contained and does not depend on Vite assets:

```bash
npm ci
npm run build
```

Tenant provisioning and permanent deletion are background operations. A queue worker is therefore required before using the Control Center to create or delete tenants. The starter uses the central database queue by default.

For a supervised process manager, keep a normal Laravel queue worker running and restart it after deployments. On shared cPanel hosting, a practical alternative is a cron-driven worker that exits when the queue becomes empty, for example once per minute:

```bash
cd /home/<cpanel-user>/<application-domain> && /path/to/php artisan queue:work --queue=default --stop-when-empty --tries=1 --timeout=600
```

When `flock` is available, use it to prevent cron from launching overlapping workers for the same application. The database queue `retry_after` default is intentionally longer than the tenant-operation job timeout so a second worker cannot pick up the same long-running job prematurely.

After installation and queue-worker setup, continue at `/central/login` and provision a disposable tenant to validate real cPanel subdomain, database, HTTPS and isolation behavior.

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

The starter defaults to database-backed sessions/cache and a central database queue. Restart long-running queue workers after deployments.

Production HTTPS enforcement defaults on when `APP_ENV=production`; only set `FORCE_HTTPS=false` when there is an intentional deployment-specific reason.

Never commit production `.env` files, cPanel API tokens, or database/SMTP credentials.
