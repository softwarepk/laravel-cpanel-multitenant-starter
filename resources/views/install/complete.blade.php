<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation complete</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6f8; }
        main { width: min(760px, calc(100% - 32px)); margin: 64px auto; }
        .card { background: #fff; border: 1px solid #dce2ea; border-radius: 16px; padding: 32px; box-shadow: 0 3px 10px rgba(24,34,52,.05); }
        .badge { display: inline-block; background: #eaf8f1; color: #147548; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 800; }
        h1 { font-size: 34px; margin: 14px 0 10px; }
        p { color: #5c687c; line-height: 1.6; }
        dl { display: grid; grid-template-columns: 180px 1fr; gap: 10px 16px; background: #f8f9fb; border-radius: 10px; padding: 18px; margin: 24px 0; }
        dt { font-weight: 700; } dd { margin: 0; word-break: break-all; }
        .status { border-radius: 10px; padding: 16px; margin: 22px 0; }
        .status-ok { background: #edf9f2; border: 1px solid #bfe3cd; color: #17633f; }
        .status-warn { background: #fff8e6; border: 1px solid #efd48b; color: #6b4f05; }
        .status p { color: inherit; margin: 6px 0 0; font-size: 13px; }
        pre { white-space: pre-wrap; word-break: break-all; background: #172033; color: #f4f7fb; border-radius: 9px; padding: 13px; font-size: 12px; line-height: 1.5; margin: 12px 0 0; }
        a { display: inline-block; background: #172033; color: white; text-decoration: none; font-weight: 700; border-radius: 9px; padding: 12px 18px; }
        @media (max-width: 620px) { .card { padding: 22px; } dl { grid-template-columns: 1fr; } dt { margin-top: 5px; } }
    </style>
</head>
<body>
<main>
    <section class="card">
        <span class="badge">Installation complete</span>
        <h1>{{ $queue['configured'] ? 'The deployment is ready for staging.' : 'The application is installed.' }}</h1>
        <p>The production environment has been written, the central database has been migrated, and the first Control Center administrator has been created. The installer is now locked.</p>
        <dl>
            <dt>Control Center</dt><dd>https://{{ $centralDomain }}</dd>
            <dt>Central database</dt><dd>{{ $centralDatabase }}</dd>
            <dt>Tenant DB user</dt><dd>{{ $tenantDatabaseUser }}</dd>
            <dt>Queue schedule</dt><dd>{{ $queue['schedule'] }}</dd>
        </dl>

        @if($queue['configured'])
            <div class="status status-ok">
                <strong>✓ Queue worker configured</strong>
                <p>{{ $queue['message'] }}</p>
            </div>
        @else
            <div class="status status-warn">
                <strong>Queue worker requires one manual action</strong>
                <p>{{ $queue['message'] }} Tenant creation and permanent deletion should not be used until a queue worker is running.</p>
                @if($queue['command'])
                    <p>cPanel Cron Jobs schedule: <strong>{{ $queue['schedule'] }}</strong></p>
                    <pre>{{ $queue['command'] }}</pre>
                @endif
            </div>
        @endif

        <p>The next stage is to sign in and provision a disposable tenant so cPanel subdomain, database, migration, HTTPS, queue execution and isolation behavior can be validated end to end.</p>
        <a href="/central/login">Open Control Center</a>
    </section>
</main>
</body>
</html>
