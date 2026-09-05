# Deployment

See `docs/CPANEL-OPERATIONS.md` for the complete multi-tenant cPanel operating model.

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

The starter defaults to database-backed sessions/cache and a central database queue. If the application dispatches asynchronous jobs, configure a cPanel worker or cron-driven `php artisan queue:work --stop-when-empty` process. Restart long-running workers after deployments.

Production HTTPS enforcement defaults on when `APP_ENV=production`; only set `FORCE_HTTPS=false` when there is an intentional deployment-specific reason.

Never commit production `.env` files, cPanel API tokens, or database/SMTP credentials.
