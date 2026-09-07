<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation complete</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; }
        body { margin: 0; background: #f6f7f9; }
        main { width: min(720px, calc(100% - 32px)); margin: 72px auto; }
        .card { background: #fff; border: 1px solid #dde2ea; border-radius: 16px; padding: 32px; box-shadow: 0 3px 10px rgba(24,34,52,.05); }
        .badge { display: inline-block; background: #eaf8f1; color: #147548; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 800; }
        h1 { font-size: 34px; margin: 14px 0 10px; }
        p { color: #5c687c; line-height: 1.6; }
        dl { display: grid; grid-template-columns: 180px 1fr; gap: 10px 16px; background: #f8f9fb; border-radius: 10px; padding: 18px; margin: 24px 0; }
        dt { font-weight: 700; } dd { margin: 0; word-break: break-all; }
        a { display: inline-block; background: #172033; color: white; text-decoration: none; font-weight: 700; border-radius: 9px; padding: 12px 18px; }
        code { background: #f0f2f5; border-radius: 5px; padding: 2px 5px; }
    </style>
</head>
<body>
<main>
    <section class="card">
        <span class="badge">Installation complete</span>
        <h1>The deployment is ready for staging.</h1>
        <p>The production environment has been written, the central database has been migrated, and the first Control Center administrator has been created. The installer is now locked.</p>
        <dl>
            <dt>Control Center</dt><dd>https://{{ $centralDomain }}</dd>
            <dt>Central database</dt><dd>{{ $centralDatabase }}</dd>
            <dt>Tenant DB user</dt><dd>{{ $tenantDatabaseUser }}</dd>
            <dt>Tenant lifecycle</dt><dd>Synchronous — no queue worker or cron required</dd>
        </dl>
        <p>Before using the normal UI, make sure the frontend build is present at <code>public/build/manifest.json</code>. Then sign in and provision the first disposable tenant so cPanel subdomain, database, migration, HTTPS and isolation behavior can be validated end to end.</p>
        <a href="/central/login">Open Control Center</a>
    </section>
</main>
</body>
</html>
