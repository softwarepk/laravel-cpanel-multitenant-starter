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

## Guided setup flow

The installer presents one concern at a time rather than one large configuration form:

1. **Environment** — PHP/extensions, writability, document root and HTTPS checks.
2. **Application** — application URL, Control Center hostname, tenant platform root and DNS/document-root settings.
3. **cPanel** — API host/user/token plus an explicit live connection test.
4. **Databases** — central and tenant database hosts, users, passwords and naming.
5. **Queue worker** — detected PHP CLI/flock paths and the proposed background-worker strategy.
6. **Administrator** — first Control Center administrator and initial tenant-user registration options.
7. **Review** — a non-secret summary and explicit final confirmation before installation starts.

With JavaScript enabled, Back/Continue navigation progressively reveals these sections and validates the current section before moving forward. The cPanel step requires a successful live preflight after the relevant connection fields are entered. The final installation is still one server-side submission so secrets do not need to be persisted in a browser/server wizard session before an application key or normal Laravel session exists.

The HTML remains usable without the wizard JavaScript: all sections are present in the document and the final server-side validation remains authoritative.

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

The queue step also attempts to discover a PHP CLI binary matching the running PHP major/minor version and the Linux `flock` utility. Optional deployment-specific overrides are available through `INSTALLER_QUEUE_PHP_BINARY` and `INSTALLER_QUEUE_FLOCK_BINARY` if automatic discovery is not correct.

The cPanel **Test connection** action is read-only. It proves that the submitted token can reach cPanel, that the account manages the hostname currently serving the installer, that the document root matches this Laravel `public` directory, that the tenant platform root belongs to the account, and that cPanel can report the account's MySQL/MariaDB host. The final installation repeats the authoritative checks before changing resources.

Existing non-secret `.env` values may be used as defaults when resuming an incomplete installation. Secrets are never redisplayed.

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

## Queue worker setup

Tenant creation and permanent deletion are background operations, so a production installation requires a queue worker.

For ordinary shared cPanel hosting, the installer prefers a once-per-minute cron entry that starts a short-lived database worker and exits when the queue is empty. When PHP CLI and `flock` are detected, the generated command follows this pattern:

```bash
/path/to/flock -n /path/to/storage/framework/queue-worker.lock /path/to/php /path/to/artisan queue:work database --queue=default --stop-when-empty --tries=1 --timeout=600
```

The application sets the database queue `retry_after` to 900 seconds, which remains longer than the 600-second tenant-operation worker timeout.

The installer manages this cron idempotently:

1. lists the account's cron entries;
2. recognizes only the marker for this exact deployment path;
3. reuses an exact existing worker entry;
4. refuses to silently replace a conflicting managed entry;
5. adds a missing once-per-minute entry;
6. lists cron again and verifies that the new entry exists.

cPanel currently exposes Cron `listcron`/`add_line` only through deprecated cPanel API 2; no UAPI equivalent exists. API 2 use is therefore isolated to this installer operation. If the host disables API 2/Cron access, or if PHP CLI/flock cannot be detected safely, the application installation can still complete but the success screen clearly marks **Queue worker requires one manual action** and shows the applicable worker command when one can be generated. Do not create/delete tenants until that worker action is resolved.

A VPS or managed deployment may instead use Supervisor/systemd or another persistent process manager. In that case the generated cPanel cron is not required.

## Installation transaction boundary

cPanel, MySQL and the filesystem do not form one transaction. The installer is therefore resumable rather than pretending all external work can roll back atomically.

On final submission it:

1. creates an installation-pending marker;
2. validates cPanel ownership/domain/document-root/database-host information again;
3. creates or verifies the central and tenant DB users;
4. verifies the submitted database passwords by connecting to MySQL/MariaDB;
5. creates or verifies the central database and grants the central user access;
6. verifies a fresh central database is empty;
7. generates or preserves the Laravel `APP_KEY`;
8. writes production `.env` atomically with restrictive file permissions where supported;
9. configures the current request with the new central connection;
10. runs central migrations;
11. creates or updates the submitted first Control Center administrator;
12. creates/verifies the cPanel queue-worker cron where automatic setup is available;
13. clears stale Laravel optimization/cache artifacts;
14. writes an installation-complete marker and removes the pending state;
15. records `INSTALLATION_COMPLETE=true` in `.env`.

If an external step fails before completion, the pending marker remains. Returning to `/install` resumes the workflow and reuses resources that were already created where safe. Secrets must be re-entered because the installer deliberately does not persist them in intermediate wizard state.

Queue cron inability is treated differently from a database/domain/bootstrap failure: the application may finish installing, but the success screen prominently reports the required manual worker action.

## After installation

The installer locks itself. Requests to `/install` on the central domain redirect to `/central/login`; tenant hosts do not expose it.

If the completion screen reports that the queue worker was configured, proceed to the Control Center. If it reports a manual worker action, complete that action first.

Then sign in and provision at least two disposable tenants. Validate real cPanel subdomain creation, tenant database creation/privileges, queued tenant migrations/initial administrators, HTTPS activation, session/cache/storage/database isolation, custom-domain lifecycle, suspension/reactivation and permanent deletion history before treating the hosting environment as production-ready.
