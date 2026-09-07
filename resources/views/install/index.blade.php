<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install {{ $values['app_name'] ?? 'Multi-Tenant Starter' }}</title>
    <script>document.documentElement.classList.add('wizard-enabled');</script>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6f8; color: #172033; }
        main { width: min(1040px, calc(100% - 32px)); margin: 34px auto 72px; }
        h1 { margin: 0 0 8px; font-size: clamp(28px, 4vw, 38px); letter-spacing: -.02em; }
        h2 { margin: 0; font-size: 21px; }
        h3 { margin: 0 0 12px; font-size: 15px; }
        p { line-height: 1.55; }
        .lead { color: #59657a; margin: 0; max-width: 760px; }
        .shell { margin-top: 28px; display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 22px; align-items: start; }
        .steps { position: sticky; top: 20px; background: #fff; border: 1px solid #dce2ea; border-radius: 14px; padding: 10px; box-shadow: 0 2px 6px rgba(24,34,52,.04); }
        .step { display: flex; gap: 10px; align-items: center; padding: 11px 10px; border-radius: 9px; color: #667085; font-size: 13px; font-weight: 650; }
        .step-number { width: 25px; height: 25px; border: 1px solid #d0d7e2; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; font-size: 12px; background: #fff; }
        .step.active { color: #172033; background: #eef3fb; }
        .step.active .step-number { background: #172033; border-color: #172033; color: #fff; }
        .step.complete { color: #1d6f4d; }
        .step.complete .step-number { background: #e9f7ef; border-color: #b9e0ca; color: #17633f; }
        .content { min-width: 0; }
        .card { background: #fff; border: 1px solid #dce2ea; border-radius: 14px; padding: 26px; box-shadow: 0 2px 6px rgba(24,34,52,.04); }
        .section-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 22px; }
        .eyebrow { color: #6d788c; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 5px; }
        .section-copy { color: #667085; margin: 7px 0 0; font-size: 14px; max-width: 650px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .full { grid-column: 1 / -1; }
        label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 7px; color: #39445a; }
        input[type=text], input[type=url], input[type=email], input[type=number], input[type=password] { width: 100%; border: 1px solid #cbd3df; border-radius: 9px; padding: 11px 12px; font: inherit; background: #fff; color: #172033; }
        input:focus { outline: 3px solid rgba(51,102,204,.14); border-color: #3366cc; }
        .hint { margin-top: 6px; font-size: 12px; color: #6d788c; line-height: 1.45; }
        .error { margin-top: 6px; color: #b42318; font-size: 12px; }
        .alert { border-radius: 10px; padding: 14px 16px; margin: 0 0 18px; font-size: 13px; line-height: 1.5; }
        .alert-error { background: #fff1f0; border: 1px solid #ffc9c3; color: #8c1d14; }
        .alert-warn { background: #fff8e6; border: 1px solid #efd48b; color: #6b4f05; }
        .alert-ok { background: #edf9f2; border: 1px solid #bfe3cd; color: #17633f; }
        .checks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .check { border: 1px solid #e1e6ed; border-radius: 9px; padding: 11px 12px; display: flex; gap: 10px; align-items: flex-start; }
        .dot { width: 10px; height: 10px; border-radius: 999px; margin-top: 5px; flex: 0 0 auto; }
        .ok { background: #1f9d62; }
        .bad { background: #d92d20; }
        .check strong { display: block; font-size: 13px; }
        .check span { display: block; font-size: 12px; color: #6d788c; margin-top: 2px; word-break: break-all; }
        .option { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0; margin: 0; }
        .option input { margin-top: 3px; }
        .actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid #edf0f4; margin-top: 24px; padding-top: 20px; }
        .actions-right { display: flex; gap: 10px; margin-left: auto; }
        button, .button { border: 0; border-radius: 9px; background: #172033; color: #fff; font: inherit; font-size: 14px; font-weight: 750; padding: 11px 17px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        button:hover, .button:hover { background: #26344d; }
        button.secondary, .button.secondary { color: #344054; background: #fff; border: 1px solid #cfd6df; }
        button.secondary:hover, .button.secondary:hover { background: #f7f8fa; }
        button:disabled { opacity: .45; cursor: not-allowed; }
        .connection-status { margin-top: 12px; min-height: 20px; font-size: 13px; color: #667085; line-height: 1.55; white-space: pre-line; }
        .connection-status.good { color: #17633f; background: #edf9f2; border: 1px solid #bfe3cd; border-radius: 10px; padding: 13px 15px; }
        .connection-status.fail { color: #8c1d14; background: #fff1f0; border: 1px solid #ffc9c3; border-radius: 10px; padding: 13px 15px; }
        .summary { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 0; border: 1px solid #e1e6ed; border-radius: 10px; overflow: hidden; }
        .summary dt, .summary dd { margin: 0; padding: 11px 13px; border-bottom: 1px solid #edf0f4; }
        .summary dt { background: #f8f9fb; font-size: 12px; color: #667085; font-weight: 750; }
        .summary dd { font-size: 13px; word-break: break-word; }
        .queue-box { border: 1px solid #dce2ea; border-radius: 11px; padding: 18px; background: #f9fafb; }
        .queue-status { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; margin-bottom: 12px; }
        code { background: #f0f2f5; border-radius: 5px; padding: 2px 5px; }
        pre { white-space: pre-wrap; word-break: break-all; background: #172033; color: #f4f7fb; border-radius: 9px; padding: 13px; font-size: 12px; line-height: 1.5; margin: 12px 0 0; }
        .installing { display: none; margin-top: 16px; padding: 13px 15px; border-radius: 9px; background: #eef3fb; color: #344054; font-size: 13px; }
        .installing.visible { display: block; }
        .wizard-enabled [data-step-panel] { display: none; }
        .wizard-enabled [data-step-panel].active { display: block; }
        @media (max-width: 820px) { .shell { grid-template-columns: 1fr; } .steps { position: static; display: flex; overflow-x: auto; padding: 8px; } .step { min-width: max-content; } }
        @media (max-width: 680px) { main { margin-top: 22px; } .card { padding: 19px; } .grid, .checks { grid-template-columns: 1fr; } .full { grid-column: auto; } .summary { grid-template-columns: 1fr; } .summary dt { border-bottom: 0; padding-bottom: 3px; } .summary dd { padding-top: 3px; } .actions { align-items: stretch; flex-direction: column; } .actions-right { width: 100%; margin: 0; } .actions-right button { flex: 1; } }
    </style>
</head>
<body>
<main>
    <h1>First-run deployment</h1>
    <p class="lead">A guided setup will verify this server, configure the application and databases, prepare background processing, and create the first Control Center administrator.</p>

    @if ($pending)
        <div class="alert alert-warn" style="margin-top:20px"><strong>Resuming an incomplete installation.</strong> Existing cPanel resources will be verified and reused where possible. Secret fields are intentionally not redisplayed.</div>
    @endif
    @if ($globalError)
        <div class="alert alert-error" style="margin-top:20px"><strong>Installation did not complete.</strong><br>{{ $globalError }}<br><span style="font-size:12px">Re-enter the secret fields as you continue; secrets are never redisplayed after a failed or interrupted submission.</span></div>
    @endif

    <div class="shell">
        <nav class="steps" aria-label="Installation progress">
            @foreach(['Environment', 'Application', 'cPanel', 'Databases', 'Queue worker', 'Administrator', 'Review'] as $index => $label)
                <div class="step" data-step-indicator="{{ $index + 1 }}"><span class="step-number">{{ $index + 1 }}</span><span>{{ $label }}</span></div>
            @endforeach
        </nav>

        <div class="content">
            <form id="installer-form" method="post" action="/install" autocomplete="off" novalidate>
                <section class="card" data-step-panel="1">
                    <div class="section-head">
                        <div><div class="eyebrow">Step 1 of 7</div><h2>Environment check</h2><p class="section-copy">Confirm that Laravel can safely complete setup on this hosting account before any credentials or database changes are submitted.</p></div>
                    </div>
                    <div class="checks">
                        @foreach ($checks as $check)
                            <div class="check">
                                <span class="dot {{ $check['ok'] ? 'ok' : 'bad' }}"></span>
                                <div><strong>{{ $check['label'] }}</strong><span>{{ $check['detail'] }}</span></div>
                            </div>
                        @endforeach
                    </div>
                    @if($checksReady)
                        <div class="alert alert-ok" style="margin-top:18px;margin-bottom:0"><strong>Environment ready.</strong> Required runtime, path, writability and HTTPS checks passed.</div>
                    @else
                        <div class="alert alert-error" style="margin-top:18px;margin-bottom:0"><strong>Correct the red checks before continuing.</strong> Reload this page after the hosting configuration has been fixed.</div>
                    @endif
                    <div class="actions">
                        <a class="button secondary" href="/install">Recheck</a>
                        <div class="actions-right"><button type="button" data-next {{ $checksReady ? '' : 'disabled' }}>Continue</button></div>
                    </div>
                </section>

                <section class="card" data-step-panel="2">
                    <div class="section-head"><div><div class="eyebrow">Step 2 of 7</div><h2>Application</h2><p class="section-copy">Confirm the central hostname, tenant platform root and shared Laravel document root. Discovered values remain editable where appropriate.</p></div></div>
                    <div class="grid">
                        <div><label for="app_name">Application name</label><input id="app_name" name="app_name" type="text" value="{{ $values['app_name'] ?? '' }}" required>@foreach($errors['app_name'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="app_url">Application URL</label><input id="app_url" name="app_url" type="url" value="{{ $values['app_url'] ?? '' }}" required>@foreach($errors['app_url'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="central_domain">Control Center domain</label><input id="central_domain" name="central_domain" type="text" value="{{ $values['central_domain'] ?? '' }}" required><div class="hint">Must match the hostname currently serving this installer.</div>@foreach($errors['central_domain'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="platform_domain">Tenant platform root</label><input id="platform_domain" name="platform_domain" type="text" value="{{ $values['platform_domain'] ?? '' }}" required><div class="hint">Tenant URLs become <code>tenantid.&lt;platform-domain&gt;</code>.</div>@foreach($errors['platform_domain'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div class="full"><label for="document_root">Shared Laravel public document root</label><input id="document_root" name="document_root" type="text" value="{{ $values['document_root'] ?? '' }}" required>@foreach($errors['document_root'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div class="full"><label for="custom_domain_dns_target">Custom-domain DNS target</label><input id="custom_domain_dns_target" name="custom_domain_dns_target" type="text" value="{{ $values['custom_domain_dns_target'] ?? '' }}" required>@foreach($errors['custom_domain_dns_target'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                    </div>
                    <div class="actions"><button type="button" class="secondary" data-back>Back</button><div class="actions-right"><button type="button" data-next>Continue</button></div></div>
                </section>

                <section class="card" data-step-panel="3">
                    <div class="section-head"><div><div class="eyebrow">Step 3 of 7</div><h2>cPanel connection</h2><p class="section-copy">Use an API token for the account that actually manages this deployment. The token is used for the live test and final installation, and is never redisplayed by the installer.</p></div></div>
                    <div class="grid">
                        <div><label for="cpanel_host">cPanel API host</label><input id="cpanel_host" name="cpanel_host" type="text" value="{{ $values['cpanel_host'] ?? '' }}" required><div class="hint">Public hostname with a trusted TLS certificate.</div>@foreach($errors['cpanel_host'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="cpanel_port">Secure API port</label><input id="cpanel_port" name="cpanel_port" type="number" value="{{ $values['cpanel_port'] ?? 2083 }}" required>@foreach($errors['cpanel_port'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="cpanel_user">cPanel account user</label><input id="cpanel_user" name="cpanel_user" type="text" value="{{ $values['cpanel_user'] ?? '' }}" required>@foreach($errors['cpanel_user'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="cpanel_token">cPanel API token</label><input id="cpanel_token" name="cpanel_token" type="password" value="" autocomplete="new-password" required><div class="hint">The token must manage the current hostname and tenant platform root.</div>@foreach($errors['cpanel_token'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                    </div>
                    <div style="margin-top:18px"><button type="button" class="secondary" id="test-cpanel">Test cPanel connection</button><div id="cpanel-test-status" class="connection-status">Connection has not been tested in this browser session.</div></div>
                    <div class="actions"><button type="button" class="secondary" data-back>Back</button><div class="actions-right"><button type="button" data-next id="cpanel-continue" disabled>Continue</button></div></div>
                </section>

                <section class="card" data-step-panel="4">
                    <div class="section-head"><div><div class="eyebrow">Step 4 of 7</div><h2>Databases</h2><p class="section-copy">The Control Center and tenant application use separate database users. Existing users are verified with the passwords you supply; the installer never silently changes an existing database-user password.</p></div></div>
                    <h3>Central database</h3>
                    <div class="grid">
                        <div><label for="db_host">Database host</label><input id="db_host" name="db_host" type="text" value="{{ $values['db_host'] ?? 'localhost' }}" required>@foreach($errors['db_host'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="db_port">Database port</label><input id="db_port" name="db_port" type="number" value="{{ $values['db_port'] ?? 3306 }}" required>@foreach($errors['db_port'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="central_db_name">Central database name</label><input id="central_db_name" name="central_db_name" type="text" value="{{ $values['central_db_name'] ?? '' }}" required>@foreach($errors['central_db_name'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="central_db_user">Central database user</label><input id="central_db_user" name="central_db_user" type="text" value="{{ $values['central_db_user'] ?? '' }}" required>@foreach($errors['central_db_user'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div class="full"><label for="central_db_password">Central database user password</label><input id="central_db_password" name="central_db_password" type="password" value="" autocomplete="new-password" required>@foreach($errors['central_db_password'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                    </div>
                    <h3 style="margin-top:24px">Tenant databases</h3>
                    <div class="grid">
                        <div><label for="tenant_db_host">Tenant database host</label><input id="tenant_db_host" name="tenant_db_host" type="text" value="{{ $values['tenant_db_host'] ?? 'localhost' }}" required>@foreach($errors['tenant_db_host'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="tenant_db_port">Tenant database port</label><input id="tenant_db_port" name="tenant_db_port" type="number" value="{{ $values['tenant_db_port'] ?? 3306 }}" required>@foreach($errors['tenant_db_port'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="tenant_db_user">Tenant application DB user</label><input id="tenant_db_user" name="tenant_db_user" type="text" value="{{ $values['tenant_db_user'] ?? '' }}" required><div class="hint">This user receives access to each database as tenants are provisioned.</div>@foreach($errors['tenant_db_user'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="tenant_db_prefix">Tenant database prefix</label><input id="tenant_db_prefix" name="tenant_db_prefix" type="text" value="{{ $values['tenant_db_prefix'] ?? '' }}" required>@foreach($errors['tenant_db_prefix'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div class="full"><label for="tenant_db_password">Tenant application DB user password</label><input id="tenant_db_password" name="tenant_db_password" type="password" value="" autocomplete="new-password" required>@foreach($errors['tenant_db_password'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                    </div>
                    <div style="margin-top:18px"><button type="button" class="secondary" id="test-database">Verify database configuration</button><div id="database-test-status" class="connection-status">Database configuration has not been verified in this browser session.</div></div>
                    <div class="actions"><button type="button" class="secondary" data-back>Back</button><div class="actions-right"><button type="button" data-next id="database-continue" disabled>Continue</button></div></div>
                </section>

                <section class="card" data-step-panel="5">
                    <div class="section-head"><div><div class="eyebrow">Step 5 of 7</div><h2>Background processing</h2><p class="section-copy">Tenant creation and permanent deletion run as queued jobs. On shared cPanel hosting, the installer prepares a once-per-minute worker that exits when the queue is empty.</p></div></div>
                    <div class="queue-box">
                        @if($queuePlan['automatic'])
                            <div class="queue-status" style="color:#17633f"><span class="dot ok" style="margin-top:0"></span>Automatic cron setup is available</div>
                            <div class="grid">
                                <div><label>Detected PHP CLI</label><div class="hint" style="font-size:13px;word-break:break-all">{{ $queuePlan['php_binary'] }}</div></div>
                                <div><label>Lock utility</label><div class="hint" style="font-size:13px;word-break:break-all">{{ $queuePlan['flock_binary'] }}</div></div>
                                <div><label>Schedule</label><div class="hint" style="font-size:13px"><code>{{ $queuePlan['schedule'] }}</code> — once per minute</div></div>
                                <div><label>Worker timeout</label><div class="hint" style="font-size:13px">600 seconds, with database retry-after set to 900 seconds</div></div>
                            </div>
                            <pre>{{ $queuePlan['command'] }}</pre>
                            <p class="hint">During final installation, cPanel Cron Jobs is checked first. A matching managed entry is reused; otherwise the installer adds one and verifies it. If cPanel does not permit automatic cron management, installation can still finish with an explicit manual action.</p>
                        @else
                            <div class="queue-status" style="color:#8a6110"><span class="dot" style="background:#d99b20;margin-top:0"></span>Manual worker setup may be required</div>
                            <p style="margin:0;color:#667085;font-size:14px">{{ $queuePlan['reason'] }}</p>
                            @if($queuePlan['worker_command'])<pre>{{ $queuePlan['worker_command'] }}</pre>@endif
                            <p class="hint">The installer will not create an overlapping-prone cron entry without a locking utility. A supervised long-running worker remains a valid alternative.</p>
                        @endif
                    </div>
                    <div class="actions"><button type="button" class="secondary" data-back>Back</button><div class="actions-right"><button type="button" data-next>Continue</button></div></div>
                </section>

                <section class="card" data-step-panel="6">
                    <div class="section-head"><div><div class="eyebrow">Step 6 of 7</div><h2>Administrator &amp; options</h2><p class="section-copy">Create the first Control Center administrator and choose the starter's initial tenant-user registration settings.</p></div></div>
                    <div class="grid">
                        <div><label for="admin_name">Administrator name</label><input id="admin_name" name="admin_name" type="text" value="{{ $values['admin_name'] ?? '' }}" required>@foreach($errors['admin_name'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="admin_email">Administrator email</label><input id="admin_email" name="admin_email" type="email" value="{{ $values['admin_email'] ?? '' }}" required>@foreach($errors['admin_email'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="admin_password">Password</label><input id="admin_password" name="admin_password" type="password" value="" autocomplete="new-password" required>@foreach($errors['admin_password'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                        <div><label for="admin_password_confirmation">Confirm password</label><input id="admin_password_confirmation" name="admin_password_confirmation" type="password" value="" autocomplete="new-password" required></div>
                    </div>
                    <div style="margin-top:20px;border-top:1px solid #edf0f4;padding-top:12px">
                        <label class="option"><input type="checkbox" name="registration" value="1" {{ ($values['registration'] ?? false) ? 'checked' : '' }}><span><strong>Enable public tenant-user registration</strong><br><span class="hint">Off is the safer default for business applications.</span></span></label>
                        <label class="option"><input type="checkbox" name="verification" value="1" {{ ($values['verification'] ?? true) ? 'checked' : '' }}><span><strong>Require tenant-user email verification</strong><br><span class="hint">A real mail transport can be configured after installation; the starter initially uses the log mailer.</span></span></label>
                    </div>
                    <div class="actions"><button type="button" class="secondary" data-back>Back</button><div class="actions-right"><button type="button" data-next>Review setup</button></div></div>
                </section>

                <section class="card" data-step-panel="7">
                    <div class="section-head"><div><div class="eyebrow">Step 7 of 7</div><h2>Review &amp; install</h2><p class="section-copy">Review the non-secret deployment details before the installer creates or verifies cPanel resources and writes the production environment.</p></div></div>
                    <dl class="summary">
                        <dt>Application</dt><dd data-review="app_name"></dd>
                        <dt>Control Center</dt><dd data-review="app_url"></dd>
                        <dt>Tenant platform root</dt><dd data-review="platform_domain"></dd>
                        <dt>Document root</dt><dd data-review="document_root"></dd>
                        <dt>cPanel account</dt><dd data-review-combined="cpanel"></dd>
                        <dt>cPanel API token</dt><dd data-secret-review="cpanel_token"></dd>
                        <dt>Central database</dt><dd data-review="central_db_name"></dd>
                        <dt>Central DB user</dt><dd data-review="central_db_user"></dd>
                        <dt>Tenant DB user</dt><dd data-review="tenant_db_user"></dd>
                        <dt>Tenant DB prefix</dt><dd data-review="tenant_db_prefix"></dd>
                        <dt>Queue worker</dt><dd>{{ $queuePlan['automatic'] ? 'Automatic cPanel cron will be attempted' : 'Manual action may be required' }}</dd>
                        <dt>Administrator</dt><dd data-review="admin_email"></dd>
                    </dl>
                    <div style="margin-top:18px">
                        <label class="option"><input type="checkbox" name="confirm_install" value="1" required><span><strong>I understand this will create or reuse cPanel database resources, write the production <code>.env</code>, run central migrations, create the first Control Center administrator, and configure the queue worker where supported.</strong></span></label>
                        @foreach($errors['confirm_install'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach
                    </div>
                    <div id="installing-message" class="installing"><strong>Installation is running.</strong> Keep this page open. If a hosting operation fails, the installer will preserve resumable state and report the failed step.</div>
                    <div class="actions"><button type="button" class="secondary" data-back>Back</button><div class="actions-right"><button type="submit" id="install-submit">Install &amp; configure</button></div></div>
                </section>
            </form>
        </div>
    </div>
</main>
<script>
(() => {
    const form = document.getElementById('installer-form');
    if (!form) return;

    const panels = Array.from(document.querySelectorAll('[data-step-panel]'));
    const indicators = Array.from(document.querySelectorAll('[data-step-indicator]'));
    const maxStep = panels.length;
    let currentStep = Math.min(maxStep, Math.max(1, Number(@json($initialStep)) || 1));
    let cpanelVerified = false;
    let databaseVerified = false;

    const field = (name) => form.elements.namedItem(name);
    const value = (name) => {
        const control = field(name);
        return control instanceof HTMLInputElement ? control.value.trim() : '';
    };

    const validatePanel = (panel) => {
        const controls = Array.from(panel.querySelectorAll('input, select, textarea'));
        for (const control of controls) {
            if (typeof control.checkValidity === 'function' && !control.checkValidity()) {
                control.reportValidity();
                return false;
            }
        }
        if (panel.dataset.stepPanel === '6') {
            const password = field('admin_password');
            const confirmation = field('admin_password_confirmation');
            if (password instanceof HTMLInputElement && confirmation instanceof HTMLInputElement && password.value !== confirmation.value) {
                confirmation.setCustomValidity('The password confirmation does not match.');
                confirmation.reportValidity();
                confirmation.setCustomValidity('');
                return false;
            }
        }
        return true;
    };

    const updateReview = () => {
        document.querySelectorAll('[data-review]').forEach((node) => {
            node.textContent = value(node.dataset.review) || '—';
        });
        const cpanel = document.querySelector('[data-review-combined="cpanel"]');
        if (cpanel) cpanel.textContent = [value('cpanel_user'), value('cpanel_host')].filter(Boolean).join(' @ ') || '—';
        document.querySelectorAll('[data-secret-review]').forEach((node) => {
            node.textContent = value(node.dataset.secretReview) ? 'Provided (not displayed)' : 'Not provided';
        });
    };

    const showStep = (step) => {
        currentStep = Math.min(maxStep, Math.max(1, step));
        panels.forEach((panel) => panel.classList.toggle('active', Number(panel.dataset.stepPanel) === currentStep));
        indicators.forEach((indicator) => {
            const number = Number(indicator.dataset.stepIndicator);
            indicator.classList.toggle('active', number === currentStep);
            indicator.classList.toggle('complete', number < currentStep);
        });
        if (currentStep === 7) updateReview();
        window.scrollTo({top: 0, behavior: 'smooth'});
    };

    document.querySelectorAll('[data-next]').forEach((button) => {
        button.addEventListener('click', () => {
            const panel = button.closest('[data-step-panel]');
            if (!panel || !validatePanel(panel)) return;
            if (Number(panel.dataset.stepPanel) === 3 && !cpanelVerified) return;
            if (Number(panel.dataset.stepPanel) === 4 && !databaseVerified) return;
            showStep(currentStep + 1);
        });
    });
    document.querySelectorAll('[data-back]').forEach((button) => button.addEventListener('click', () => showStep(currentStep - 1)));

    const cpanelContinue = document.getElementById('cpanel-continue');
    const cpanelTest = document.getElementById('test-cpanel');
    const cpanelStatus = document.getElementById('cpanel-test-status');
    const databaseContinue = document.getElementById('database-continue');
    const databaseTest = document.getElementById('test-database');
    const databaseStatus = document.getElementById('database-test-status');
    const cpanelFields = ['central_domain', 'platform_domain', 'document_root', 'cpanel_host', 'cpanel_port', 'cpanel_user', 'cpanel_token'];
    const databaseFields = ['cpanel_host', 'cpanel_port', 'cpanel_user', 'cpanel_token', 'db_host', 'db_port', 'central_db_name', 'central_db_user', 'central_db_password', 'tenant_db_host', 'tenant_db_port', 'tenant_db_user', 'tenant_db_password', 'tenant_db_prefix'];

    const resetDatabaseVerification = () => {
        databaseVerified = false;
        if (databaseContinue) databaseContinue.disabled = true;
        if (databaseStatus) {
            databaseStatus.className = 'connection-status';
            databaseStatus.textContent = 'Database configuration changed; verify it again before continuing.';
        }
    };

    const resetCpanelVerification = () => {
        cpanelVerified = false;
        if (cpanelContinue) cpanelContinue.disabled = true;
        if (cpanelStatus) {
            cpanelStatus.className = 'connection-status';
            cpanelStatus.textContent = 'Connection changed; test cPanel again before continuing.';
        }
        resetDatabaseVerification();
    };
    cpanelFields.forEach((name) => field(name)?.addEventListener('input', resetCpanelVerification));
    databaseFields.filter((name) => !cpanelFields.includes(name)).forEach((name) => field(name)?.addEventListener('input', resetDatabaseVerification));

    cpanelTest?.addEventListener('click', async () => {
        const panel = document.querySelector('[data-step-panel="3"]');
        if (!panel || !validatePanel(panel)) return;
        cpanelTest.disabled = true;
        if (cpanelStatus) {
            cpanelStatus.className = 'connection-status';
            cpanelStatus.textContent = 'Testing cPanel ownership, document root and platform domain…';
        }

        try {
            const response = await fetch('/install/cpanel-check', {
                method: 'POST',
                body: new FormData(form),
                headers: {Accept: 'application/json'},
            });
            const data = await response.json();
            if (!response.ok) throw data;
            cpanelVerified = true;
            if (cpanelContinue) cpanelContinue.disabled = false;
            if (cpanelStatus) {
                cpanelStatus.className = 'connection-status good';
                cpanelStatus.textContent = `✓ ${data.message}\nMySQL/MariaDB host reported by cPanel: ${data.database_host}.`;
            }
        } catch (data) {
            cpanelVerified = false;
            if (cpanelContinue) cpanelContinue.disabled = true;
            const details = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'The cPanel connection could not be verified.');
            if (cpanelStatus) {
                cpanelStatus.className = 'connection-status fail';
                cpanelStatus.textContent = details;
            }
        } finally {
            cpanelTest.disabled = false;
        }
    });

    databaseTest?.addEventListener('click', async () => {
        const panel = document.querySelector('[data-step-panel="4"]');
        if (!panel || !validatePanel(panel)) return;
        databaseTest.disabled = true;
        if (databaseStatus) {
            databaseStatus.className = 'connection-status';
            databaseStatus.textContent = 'Checking database host, names, existing users and credentials…';
        }

        try {
            const response = await fetch('/install/database-check', {
                method: 'POST',
                body: new FormData(form),
                headers: {Accept: 'application/json'},
            });
            const data = await response.json();
            if (!response.ok) throw data;
            databaseVerified = true;
            if (databaseContinue) databaseContinue.disabled = false;
            if (databaseStatus) {
                const checks = Array.isArray(data.checks) ? data.checks.map((check) => `✓ ${check}`).join('\n') : '';
                databaseStatus.className = 'connection-status good';
                databaseStatus.textContent = `✓ ${data.message}${checks ? `\n${checks}` : ''}`;
            }
        } catch (data) {
            databaseVerified = false;
            if (databaseContinue) databaseContinue.disabled = true;
            const details = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'The database configuration could not be verified.');
            if (databaseStatus) {
                databaseStatus.className = 'connection-status fail';
                databaseStatus.textContent = details;
            }
        } finally {
            databaseTest.disabled = false;
        }
    });

    form.addEventListener('submit', (event) => {
        const review = document.querySelector('[data-step-panel="7"]');
        if (review && !validatePanel(review)) {
            event.preventDefault();
            return;
        }
        const submit = document.getElementById('install-submit');
        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Installing…';
        }
        document.getElementById('installing-message')?.classList.add('visible');
    });

    showStep(currentStep);
})();
</script>
</body>
</html>