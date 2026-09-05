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

Never commit production `.env` files, cPanel API tokens, or database/SMTP credentials.
