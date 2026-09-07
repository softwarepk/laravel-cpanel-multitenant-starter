# Production Checklist

## Purpose

This repository is a starter, not a finished production SaaS product. It provides tenant isolation, a central control plane, cPanel-oriented provisioning, and safe extension points. An application built from the starter should review the items below before accepting real customer data.

Not every item needs to be enabled for every project. The goal is to make production decisions explicit rather than silently assuming the starter's development-friendly defaults are appropriate everywhere.

## 1. Application and environment

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure a real central MySQL/MariaDB database or another production-grade central database supported by the application.
- Set a strong production `APP_KEY` and keep `.env` outside source control.
- Confirm `CENTRAL_DOMAINS` contains only intended Control Center hostnames.
- Confirm `TENANT_PLATFORM_DOMAIN` and `TENANT_PLATFORM_DOCUMENT_ROOT` point to the production deployment.
- Run `php artisan optimize` after production configuration is finalized.

## 2. Password policy

The starter intentionally defaults to a simple password policy: a minimum of eight characters.

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

Tenant/database identity reuse is allowed only after the latest matching deletion record completed cleanly with no failed cleanup results. Started, failed, warning-bearing, or otherwise incomplete deletion history must continue to reserve that identity.

## 5. Real cPanel staging validation

Before provisioning production tenants, validate the exact hosting account end to end:

1. Central hostname resolves and serves the Control Center over trusted HTTPS.
2. cPanel API token authenticates successfully.
3. The configured tenant DB user exists.
4. A test tenant platform subdomain can be created with the expected document root.
5. A tenant database can be created and the tenant DB user receives access.
6. Tenant migrations run successfully.
7. Initial tenant administrator creation succeeds.
8. AutoSSL/trusted HTTPS becomes available on the platform hostname.
9. Custom-domain creation, verification, primary-domain switching, and removal work on the actual host.
10. Suspension/reactivation works.
11. A suspended tenant cannot be reactivated by directly invoking the HTTPS-check endpoint.
12. Domain/configuration mutations are blocked while deletion is running or cleanup is unresolved.
13. Permanent deletion records what was removed and what still needs manual cleanup.
14. Navigating away during create/delete does not stop the queued operation.
15. A stale/retired lifecycle job does not continue into later stages after a newer retry takes ownership.

The Control Center's provisioning-readiness check verifies configuration presence. It is not a substitute for the real staging exercise above.

## 6. Queue execution and tenant operation timing

Tenant creation and permanent deletion are built-in background operations. A production queue worker is therefore required before those Control Center actions are used.

The starter's tenant-operation jobs have a 600-second timeout. The database queue `retry_after` default is 900 seconds and must remain comfortably above the job timeout so a second worker cannot reserve the same job prematurely.

Stale-operation recovery deliberately waits longer again (20 minutes in the built-in controllers). Do not shorten that window to the job timeout itself without redesigning the stale-generation safeguards.

Provisioning jobs carry a generation/operation ID. Running provisioning revalidates that ID between major stages. Deletion jobs use a durable deletion record and revalidate that the record is still active before entering later destructive stages.

## 7. Queue workers

The database queue is central and tenant-aware jobs retain the originating tenant ID. Database jobs are configured to dispatch after surrounding transactions commit.

Use either:

- a persistent supervised worker where the host supports it; or
- a cron-driven worker such as `php artisan queue:work --queue=default --stop-when-empty --tries=1 --timeout=600`.

When cron is used, prefer `flock` or another mutual-exclusion mechanism to avoid overlapping workers.

Restart long-running workers after deployments and review failed jobs operationally.

Do not switch production to `QUEUE_CONNECTION=sync` unless you deliberately redesign the built-in queued tenant lifecycle as well.

## 8. Sessions, cookies, proxies, and HTTPS

Database-backed sessions are tenant-local because tenant resolution occurs before session handling.

For production, review:

- `SESSION_SECURE_COOKIE=true` when HTTPS reaches Laravel directly;
- `SESSION_HTTP_ONLY=true`;
- an appropriate `SESSION_SAME_SITE` value;
- leaving `SESSION_DOMAIN` unset unless cross-subdomain cookies are intentionally required.

Avoid broadly sharing the tenant session cookie across `*.TENANT_PLATFORM_DOMAIN` unless the application explicitly needs that behavior.

If TLS terminates at Cloudflare, a load balancer, or another reverse proxy, configure Laravel's trusted-proxy handling correctly before relying on `isSecure()`, secure cookies, redirects, or generated HTTPS URLs.

HTTPS verification must not be treated as a generic lifecycle activation route. A suspended or deleting tenant must remain unavailable even when its certificate is valid.

## 9. Tenant files

The starter switches both `local` and `public` storage roots with tenant context. Application code should use Laravel `Storage` APIs rather than manually building tenant paths.

Before exposing customer uploads through direct public URLs, test the final file-delivery design with hostile cases:

- the same filename in two tenants;
- copied file URLs between tenant hostnames;
- custom-domain aliases;
- download routes using guessed record IDs.

A database-per-tenant boundary does not protect a file that an application deliberately exposes through a shared public path.

## 10. Custom domains

Custom domains are managed by central administrators through the Control Center. The Control Center can remove a non-primary custom domain and will first verify that the cPanel domain still points at the application's configured document root.

All domain mutations must remain guarded server-side by tenant lifecycle state. Do not rely on Blade/UI visibility alone to prevent domain changes during provisioning, failed cleanup, or deletion.

If a future application supports externally managed/manual domain records, distinguish those from platform-managed domains before automatically deleting infrastructure.

cPanel currently requires deprecated API 2 functions for some addon-domain operations because no equivalent UAPI operation exists. Keep this implementation behind the provided contracts/services and re-check compatibility when upgrading cPanel or changing hosts.

## 11. Tenant deletion and retention

Permanent deletion of an operational tenant requires suspension first, exact tenant-ID confirmation, and the current central administrator password. A tenant whose provisioning failed may be cleaned up directly because it may have partial infrastructure that should not remain stranded.

Deletion is queued. Before dispatch, the application creates a durable deletion record containing the tenant/database/domain snapshot. The job attempts to remove:

- the platform hostname;
- managed custom domains;
- tenant storage;
- the tenant database;
- the central tenant record.

Progress is stored after each external stage. The running job revalidates that its deletion record is still active before entering subsequent destructive stages. Stale recovery must retire the previous record before a new deletion attempt is created.

A failure in cPanel/filesystem/database cleanup does not automatically block removal of the central tenant record. A durable deletion record is retained under **Central Activity**, including the original tenant/database/domain snapshot and the result/error for each cleanup step.

A tenant ID/database identity may be reused only after a clean completed deletion. If cleanup was unresolved, failed, or completed with warnings, choose another identity or resolve the infrastructure cleanup first.

Before production, define the application's own backup, legal-hold, and data-retention policy. Take a final backup before deletion when required.

## 12. Backups and recovery

Backups must cover:

- the central database;
- every tenant database;
- tenant storage roots;
- deployment configuration/secrets through a secure secrets-management or backup process.

Test restoration, not only backup creation.

## 13. Email and notifications

The starter defaults to the `log` mailer. Configure a real mail transport before relying on:

- email verification;
- password reset;
- application notifications.

Verify sender/domain authentication and delivery behavior in the target environment.

## 14. Dependency and release checks

Before a production release, run locally:

```bash
composer fix
composer ci:check
npm run build
composer audit
npm audit
```

Review audit findings rather than blindly applying major-version upgrades.

GitHub Actions capacity may be unavailable. Do not treat missing remote checks as evidence of success; local quality-gate output is authoritative for this starter when Actions cannot run.

## 15. Higher-assurance deployments

This starter provides strong logical/data isolation inside one shared Laravel runtime. It does not provide process/server isolation. The default cPanel model also uses one application database user with access to the tenant databases provisioned for that application.

If the requirement is that compromise of one shared PHP runtime or shared database credential must not make other tenants technically reachable, use stronger deployment isolation such as separate credentials, containers, VMs, accounts, or deployments.
