# Production Checklist

## Purpose

This repository is a starter, not a finished production SaaS product. It provides tenant isolation, a central control plane, cPanel-oriented provisioning, and safe extension points. An application built from the starter should review the items below before accepting real customer data.

Not every item needs to be enabled for every project. The goal is to make production decisions explicit rather than silently assuming the starter's defaults are appropriate everywhere.

## 1. Application and environment

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure a real central MySQL/MariaDB database or another production-grade central database supported by the application.
- Set a strong production `APP_KEY` and keep `.env` outside source control.
- Confirm `CENTRAL_DOMAINS` contains only intended Control Center hostnames.
- Confirm `TENANT_PLATFORM_DOMAIN` and `TENANT_PLATFORM_DOCUMENT_ROOT` point to the production deployment.
- Confirm the frontend build exists at `public/build/manifest.json` before using normal application pages.
- Run `php artisan optimize` after production configuration is finalized.

## 2. Password policy

The starter intentionally defaults to a simple password policy: a minimum of eight characters. This keeps local setup and early application development straightforward.

Before production, review **Control Center → Settings → Password policy**. The policy can require:

- a higher minimum length;
- mixed uppercase/lowercase letters;
- numbers;
- symbols;
- rejection of known compromised passwords.

The same configured policy is used for normal tenant users, initial tenant administrators, password changes/resets, and central administrators.

Enabling compromised-password checking requires outbound access to Laravel's external password-checking service.

## 3. Central administrator security

- Create named central administrator accounts rather than sharing one account.
- Prefer interactive `php artisan central:admin` password entry instead of passing passwords on the command line.
- Consider MFA for central administrators if the Control Center is exposed to the public Internet or the application's risk profile warrants it. MFA is intentionally optional and is not required by the starter.
- Restrict access to the Control Center by network/VPN/IP rules when appropriate for the project.

## 4. cPanel and tenant database configuration

- `CPANEL_API_USER` is the cPanel account/API user.
- `CPANEL_API_TOKEN` should be scoped as narrowly as the hosting environment permits.
- `TENANT_DB_USERNAME` is the MySQL/MariaDB user Laravel uses for tenant databases and the same user to which the provisioning code grants privileges.
- `TENANT_DB_PASSWORD` must match that database user.
- `CPANEL_TENANT_DB_PREFIX` is optional and should contain only letters, numbers, and underscores.

Tenant database names are generated deterministically with a short hash so distinct valid tenant IDs cannot collapse onto the same database name. Once a tenant has been assigned a database name, retries continue using that persisted identity even if naming configuration changes later.

A deleted tenant ID/database identity may be reused only after the latest deletion completed cleanly. Failed, incomplete, or warning-bearing cleanup deliberately blocks reuse so residual infrastructure cannot be exposed to a later tenant.

## 5. First-run installer validation

For a fresh production-style deployment:

1. Confirm the installer Environment step is green.
2. Run the cPanel connection test and verify the reported hostname/document root/platform root are correct.
3. Run the database configuration check before final installation.
4. If a DB user already exists, use its actual existing password; the installer will not silently reset it.
5. Do not reuse a non-empty central database for a fresh installation.
6. After installation, confirm `public/build/manifest.json` exists before opening the normal Control Center UI.

The frontend assets may be built on cPanel with `npm ci && npm run build` or prebuilt elsewhere and deployed as `public/build/`.

## 6. Real cPanel staging validation

Before provisioning production tenants, validate the exact hosting account end to end:

1. Central hostname resolves and serves the Control Center over trusted HTTPS.
2. cPanel API token authenticates successfully.
3. The configured tenant DB user exists and the supplied credential works.
4. A test tenant platform subdomain can be created with the expected document root.
5. A tenant database can be created and the tenant DB user receives access.
6. Tenant migrations run successfully.
7. Initial tenant administrator creation succeeds.
8. AutoSSL/trusted HTTPS becomes available on the platform hostname.
9. Custom-domain creation, verification, primary-domain switching, and removal work on the actual host.
10. Suspension/reactivation works.
11. Permanent deletion records what was removed and what still needs manual cleanup.
12. A cleanly deleted disposable tenant ID can be reused, while an unresolved cleanup remains blocked.

The Control Center's provisioning-readiness check verifies configuration presence. It is not a substitute for the real staging exercise above.

## 7. Synchronous tenant lifecycle

Tenant provisioning and permanent deletion run synchronously by default. This is deliberate: the shared-cPanel starter should not require cron, Supervisor, systemd, Horizon, Redis, or another worker service.

Operational implications:

- keep the browser request open while a tenant is being provisioned or permanently deleted;
- make sure the hosting account's PHP/request limits are reasonable for real cPanel domain/database operations;
- failed provisioning remains recorded and can be retried without blindly recreating resources;
- failed provisioning tenants may be cleaned up directly with the same guarded permanent-deletion confirmation.

If a derived application regularly exceeds request limits or has genuinely asynchronous workloads, introducing a queue is an application-specific enhancement rather than a starter prerequisite.

## 8. Optional queues

The starter defaults to `QUEUE_CONNECTION=sync`.

Laravel's database and other queue drivers remain available. If a derived application opts into asynchronous jobs, it must also choose and operate a worker strategy appropriate to its environment. Do not assume shared cPanel supports one-minute cron jobs or persistent workers; hosting policies vary.

## 9. Sessions, cookies, proxies, and HTTPS

Database-backed sessions are tenant-local because tenant resolution occurs before session handling.

For production, review:

- `SESSION_SECURE_COOKIE=true` when HTTPS reaches Laravel directly;
- `SESSION_HTTP_ONLY=true`;
- an appropriate `SESSION_SAME_SITE` value;
- leaving `SESSION_DOMAIN` unset unless cross-subdomain cookies are intentionally required.

Avoid broadly sharing the tenant session cookie across `*.TENANT_PLATFORM_DOMAIN` unless the application explicitly needs that behavior.

If TLS terminates at Cloudflare, a load balancer, or another reverse proxy, configure Laravel's trusted-proxy handling correctly before relying on `isSecure()`, secure cookies, redirects, or generated HTTPS URLs.

## 10. Tenant files

The starter switches both `local` and `public` storage roots with tenant context. Application code should use Laravel `Storage` APIs rather than manually building tenant paths.

Before exposing customer uploads through direct public URLs, test the final file-delivery design with hostile cases:

- the same filename in two tenants;
- copied file URLs between tenant hostnames;
- custom-domain aliases;
- download routes using guessed record IDs.

A database-per-tenant boundary does not protect a file that an application deliberately exposes through a shared public path.

## 11. Custom domains

Custom domains created from the Control Center are managed by the platform. The Control Center can remove a non-primary custom domain and will first verify that the cPanel domain still points at the application's configured document root.

If a future application supports externally managed/manual domain records, distinguish those from platform-managed domains before automatically deleting infrastructure.

cPanel currently requires deprecated API 2 functions for some addon-domain operations because no equivalent UAPI operation exists. Keep this implementation behind the provided contracts/services and re-check compatibility when upgrading cPanel or changing hosts.

## 12. Tenant deletion and retention

Permanent deletion requires an operational tenant to be suspended first, exact tenant-ID confirmation, and the current central administrator password. A tenant whose provisioning failed may be cleaned up directly because it never reached an operational state.

Deletion is intentionally best-effort across external infrastructure. The application attempts to remove:

- the platform hostname;
- managed custom domains;
- tenant storage;
- the tenant database;
- the central tenant record.

A failure in cPanel/filesystem/database cleanup does not automatically block removal of the central tenant record. A durable deletion record is retained under **Central Activity**, including the tenant/database/domain snapshot and the result/error for each cleanup step.

Identity reuse is allowed only after the latest deletion completed cleanly. This preserves convenience without risking accidental reuse when residual infrastructure remains.

Before production, define the application's own backup, legal-hold, and data-retention policy. Take a final backup before deletion when required.

## 13. Backups and recovery

Backups must cover:

- the central database;
- every tenant database;
- tenant storage roots;
- deployment configuration/secrets through a secure secrets-management or backup process.

Test restoration, not only backup creation.

## 14. Email and notifications

The starter defaults to the `log` mailer. Configure a real mail transport before relying on:

- email verification;
- password reset;
- application notifications.

Verify sender/domain authentication and delivery behavior in the target environment.

## 15. Dependency and release checks

Before a production release, run locally:

```bash
composer fix
composer ci:check
npm run build
composer audit
npm audit
```

Review audit findings rather than blindly applying major-version upgrades.

GitHub Actions in this starter are manual by default. Projects may enable PR/push triggers when CI capacity and repository rules permit.

## 16. Higher-assurance deployments

This starter provides strong logical/data isolation inside one shared Laravel runtime. It does not provide process/server isolation. The default cPanel model also uses one application database user with access to the tenant databases provisioned for that application.

If the requirement is that compromise of one shared PHP runtime or shared database credential must not make other tenants technically reachable, use stronger deployment isolation such as separate credentials, containers, VMs, accounts, or deployments.
