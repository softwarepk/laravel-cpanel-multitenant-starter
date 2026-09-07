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

Configure the cPanel domain document root as:

```text
/home/<cpanel-user>/<application-domain>/public
```

Then browse to the application over HTTPS. A fresh production clone redirects to `/install`.

The installer UI is self-contained and does not depend on Vite assets. Normal Control Center and tenant pages do, however, so a usable deployment must also contain `public/build/manifest.json` and the generated `public/build/assets/` files. These may be produced on cPanel with `npm ci && npm run build` or prebuilt elsewhere and deployed as `public/build/`. Node.js is not required at runtime after those assets exist.

## Guided setup flow

The installer presents one concern at a time rather than one large configuration form:

1. **Environment** — PHP/extensions, writability, document root and HTTPS checks.
2. **Application** — application URL, Control Center hostname, tenant platform root and DNS/document-root settings.
3. **cPanel** — API host/user/token plus an explicit live connection test.
4. **Databases** — central and tenant database hosts, users, passwords and naming plus an explicit read-only database verification.
5. **Administrator** — first Control Center administrator and initial tenant-user registration options.
6. **Review** — a non-secret summary and explicit final confirmation before installation starts.

With JavaScript enabled, Back/Continue navigation progressively reveals these sections and validates the current section before moving forward. The cPanel and database steps require successful live checks after their relevant fields are entered. The final installation remains one server-side submission so secrets do not need to be persisted in a browser/server wizard session before an application key or normal Laravel session exists.

The HTML remains usable without wizard JavaScript: all sections are present in the document and the final server-side validation remains authoritative.

## Discovery and preflight

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

Existing non-secret `.env` values may be used as defaults when resuming an incomplete installation. Secrets are never redisplayed.

The cPanel **Test connection** action is read-only. It proves that the submitted token can reach cPanel, that the account manages the hostname currently serving the installer, that the document root matches this Laravel `public` directory, that the tenant platform root belongs to the account, and that cPanel can report the account's MySQL/MariaDB host.

The database **Verify database configuration** action is also read-only. It:

- confirms both submitted database hosts are localhost/loopback or the host reported by cPanel;
- checks whether the central database already exists;
- checks whether the central and tenant database users already exist;
- verifies submitted passwords for existing database users without changing those passwords;
- rejects an existing central database that cannot be safely inspected;
- rejects a non-empty central database for a new installation;
- allows a non-empty database only when it matches the database recorded for the interrupted installation being resumed.

Neither preflight creates, deletes, grants, or modifies cPanel resources. The final installation repeats authoritative checks before making changes.

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

Importantly, the installation-pending marker is created **after** the submitted cPanel and database configuration passes the read-only safety checks. A failed preflight therefore cannot make an unrelated existing database eligible for resume reuse.

On final submission it:

1. validates cPanel ownership/domain/document-root information again;
2. validates database hosts/users/passwords/existing central database state again;
3. creates an installation-pending marker containing the selected central database identity;
4. creates or verifies the central and tenant DB users;
5. verifies the submitted database passwords by connecting to MySQL/MariaDB;
6. creates or verifies the central database and grants the central user access;
7. verifies the central database is safe for this new/resumed installation;
8. generates or preserves the Laravel `APP_KEY`;
9. writes production `.env` atomically with restrictive file permissions where supported;
10. configures the current request with the new central connection;
11. runs central migrations;
12. creates or updates the submitted first Control Center administrator;
13. clears stale Laravel optimization/cache artifacts;
14. writes an installation-complete marker and removes the pending state;
15. records `INSTALLATION_COMPLETE=true` in `.env`.

If an external step fails after the pending marker is created, returning to `/install` resumes the workflow and reuses resources that were already created where safe. Secrets must be re-entered because the installer deliberately does not persist them in intermediate wizard state.

## Queue model

The starter does not require a queue worker for tenant creation or permanent deletion. Production installation writes `QUEUE_CONNECTION=sync`, and tenant lifecycle operations run synchronously in the Control Center request. This keeps the default shared-cPanel deployment independent of cron frequency limits, Supervisor, systemd, or other external worker infrastructure.

Applications that later need asynchronous work may opt into Laravel's other queue drivers as a deliberate deployment-specific enhancement.

## After installation

The installer locks itself. Requests to `/install` on the central domain redirect to `/central/login`; tenant hosts do not expose it.

Before using the normal UI, confirm the frontend asset manifest exists:

```text
public/build/manifest.json
```

Then sign in and provision at least two disposable tenants. Validate real cPanel subdomain creation, tenant database creation/privileges, tenant migrations, initial administrators, HTTPS activation, session/cache/storage/database isolation, custom-domain lifecycle, suspension/reactivation and permanent deletion history before treating the hosting environment as production-ready.
