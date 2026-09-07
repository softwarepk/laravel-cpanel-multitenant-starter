# First-Run Web Installer

## Purpose

The web installer is the production/cPanel bootstrap path for a freshly cloned starter. It exists so an operator can create the application domain, clone the repository, point the domain at Laravel's `public` directory, open the application URL, and finish deployment from the browser without hand-building `.env` or the central database first.

Local development continues to use the existing CLI setup flow. Existing production deployments that already have an `APP_KEY` are treated as installed for backward compatibility.

## Minimal shell preparation

A new deployment still requires Composer dependencies before Laravel can answer HTTP requests:

```bash
cd /home/<cpanel-user>/<application-domain>
git clone <repository> .
composer install --no-dev --optimize-autoloader --no-interaction
```

The installer UI is self-contained and does not depend on Vite assets, so the frontend build can run before or after first-run configuration:

```bash
npm ci
npm run build
```

Configure the cPanel domain document root as:

```text
/home/<cpanel-user>/<application-domain>/public
```

Then browse to the application over HTTPS. A fresh production clone redirects to `/install`.

## What the first page discovers

The installer pre-fills editable values where the running application can make a reasonable inference, including:

- application URL and current hostname;
- central Control Center domain;
- tenant platform root domain;
- application and Laravel `public` paths;
- likely cPanel account username from the home-directory/runtime identity;
- likely cPanel API hostname from the server hostname;
- conventional central/tenant database names and prefixes;
- PHP version and required extensions;
- storage/bootstrap-cache/environment-file writability;
- whether the web server document root already points at Laravel `public`;
- whether the current request is trusted HTTPS.

All discovered configuration fields remain editable. Existing non-secret `.env` values may be used as defaults when resuming an incomplete installation. Secrets are never redisplayed.

## Operator-supplied values

The operator supplies or confirms values that cannot safely be discovered, especially:

- cPanel API token;
- cPanel API hostname/user when discovery is not correct;
- central database name/user/password;
- tenant application database user/password and database prefix;
- first Control Center administrator name/email/password;
- optional public registration and tenant email-verification switches.

The installer uses a separate central DB user and tenant application DB user. They may not be the same account.

## Validation and security boundaries

The first-run routes are intentionally registered outside Laravel's normal `web` middleware. Before installation there may be no `APP_KEY`, session table, cache table, or central database, so the installer cannot depend on encrypted cookies, sessions, CSRF state, or normal Control Center authentication.

The installer compensates with a narrow one-time trust model:

- production first-run requests are forced to HTTPS;
- the cPanel API endpoint is restricted to secure port `2083` and publicly routable addresses;
- the submitted cPanel token must successfully authenticate;
- cPanel must report that the authenticated account manages the hostname currently serving the installer;
- cPanel's reported document root for that hostname must equal this deployment's Laravel `public` directory;
- the configured tenant platform root must also exist on the authenticated cPanel account;
- MySQL/MariaDB connections may target only localhost/loopback or the database host reported by cPanel;
- the central database user and tenant database user must be distinct;
- a fresh installation refuses to initialize a non-empty central database;
- existing cPanel DB users are never silently assigned a new password; submitted credentials must work before new privileges are granted.

Because the installer has no cookie/session authentication state, conventional CSRF does not provide useful protection here. Successful installation requires possession of valid cPanel API credentials for the account that actually serves the application hostname.

## Installation transaction boundary

cPanel, MySQL and the filesystem do not form one transaction. The installer is therefore resumable rather than pretending all external work can roll back atomically.

On submission it:

1. creates an installation-pending marker;
2. validates cPanel ownership/domain/document-root/database-host information;
3. creates or verifies the central and tenant DB users;
4. verifies the submitted database passwords by connecting to MySQL/MariaDB;
5. creates or verifies the central database and grants the central user access;
6. verifies a fresh central database is empty;
7. generates or preserves the Laravel `APP_KEY`;
8. writes production `.env` atomically with restrictive file permissions where supported;
9. configures the current request with the new central connection;
10. runs central migrations;
11. creates or updates the submitted first Control Center administrator;
12. clears stale Laravel optimization/cache artifacts;
13. writes an installation-complete marker and removes the pending state;
14. records `INSTALLATION_COMPLETE=true` in `.env`.

If an external step fails before completion, the pending marker remains. Returning to `/install` resumes the workflow and reuses resources that were already created where safe.

## After installation

The installer locks itself. Requests to `/install` on the central domain redirect to `/central/login`; tenant hosts do not expose it.

The next staging step is to sign in to the Control Center and provision at least two disposable tenants. Validate real cPanel subdomain creation, tenant database creation/privileges, tenant migrations, initial administrators, HTTPS activation, session/cache/storage/database isolation, custom-domain lifecycle, suspension/reactivation and permanent deletion history before treating the hosting environment as production-ready.
