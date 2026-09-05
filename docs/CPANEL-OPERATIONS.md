# cPanel Operations

## Scope

The starter assumes a conventional Laravel deployment on a cPanel/Linux account. It does not require containers, Kubernetes, Redis, Horizon, or a separate application platform.

The production provisioning layer can automate tenant database and domain setup through cPanel APIs. Keep cPanel credentials scoped as narrowly as practical.

## Required concepts

Configure these environment values for production:

- central application hostname(s);
- tenant platform root domain;
- tenant platform document root;
- cPanel host/port/user/token;
- tenant database prefix;
- tenant database user;
- production database connection values.

The exact keys are defined by `.env.example` and `config/central.php`.

Database sessions and database cache are intentional multi-tenant defaults: tenant resolution occurs before session/cache access, so those records follow the active tenant database. The database queue remains central and carries tenant context in the queued payload.

HTTPS enforcement defaults on in production through `APP_ENV=production`. Do not set `FORCE_HTTPS=false` unless the deployment intentionally terminates/enforces HTTPS elsewhere and the application-level redirect is not desired.

## Platform hostnames

Each tenant gets a permanent platform hostname such as:

```text
acme.tenants.example.com
```

All tenant platform hostnames point at the same Laravel `public` directory. The hostname selects tenant context; it does not select a different code deployment.

When adding a custom domain, the Control Center verifies/provisions the domain and SSL state before it can become the primary tenant URL.

## Tenant databases

Each tenant receives its own MySQL/MariaDB database. The cPanel provisioning service creates/confirms the database and associates the configured database user.

Database naming must stay within MySQL/cPanel length and character constraints. The starter validates generated names before provisioning.

Do not store tenant database passwords per tenant unless your hosting model explicitly requires separate DB users. The default cPanel pattern is one restricted account DB user with access to the tenant databases provisioned for that application.

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

1. create/confirm the tenant's permanent platform hostname;
2. create/confirm the tenant database;
3. run tenant migrations;
4. create the first tenant administrator;
5. verify trusted HTTPS;
6. activate the tenant when ready.

If HTTPS is not ready yet, the tenant stays in an HTTPS-pending provisioning state. Recheck after AutoSSL/certificate issuance completes.

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

Suspension is a logical/control-plane operation. It should block tenant application access without destroying database/files/domain records.

Use suspension when access must be stopped temporarily. Do not delete a tenant merely to disable it.

## Permanent deletion

Tenant deletion may remove database, tenant storage, and platform-domain infrastructure. It is intentionally guarded and should require deliberate administrator action.

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

## Local development

Normal automated tests should not call a real cPanel account. Infrastructure services are behind contracts so tests can substitute fakes/mocks.

A local developer should be able to exercise central/tenant routing and database isolation using local test databases without provisioning public DNS or SSL certificates.
