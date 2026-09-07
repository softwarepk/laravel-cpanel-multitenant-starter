<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install {{ $values['app_name'] ?? 'Multi-Tenant Starter' }}</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f6f7f9; color: #172033; }
        main { width: min(1080px, calc(100% - 32px)); margin: 40px auto 72px; }
        h1 { margin: 0 0 8px; font-size: clamp(28px, 4vw, 40px); }
        h2 { margin: 0 0 18px; font-size: 20px; }
        p { line-height: 1.55; }
        .lead { color: #59657a; margin: 0 0 28px; }
        .card { background: white; border: 1px solid #dde2ea; border-radius: 14px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(24, 34, 52, .04); }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .full { grid-column: 1 / -1; }
        label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 7px; color: #39445a; }
        input[type=text], input[type=url], input[type=email], input[type=number], input[type=password] { width: 100%; border: 1px solid #cfd6e1; border-radius: 9px; padding: 11px 12px; font: inherit; background: #fff; color: #172033; }
        input:focus { outline: 3px solid rgba(51, 102, 204, .14); border-color: #3366cc; }
        .hint { margin-top: 6px; font-size: 12px; color: #6d788c; }
        .error { margin-top: 6px; color: #b42318; font-size: 12px; }
        .alert { border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; }
        .alert-error { background: #fff1f0; border: 1px solid #ffc9c3; color: #8c1d14; }
        .alert-warn { background: #fff8e6; border: 1px solid #f1d48a; color: #6b4f05; }
        .checks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .check { border: 1px solid #e2e6ed; border-radius: 9px; padding: 11px 12px; display: flex; gap: 10px; align-items: flex-start; }
        .dot { width: 10px; height: 10px; border-radius: 999px; margin-top: 5px; flex: 0 0 auto; }
        .ok { background: #1f9d62; }
        .bad { background: #d92d20; }
        .check strong { display: block; font-size: 13px; }
        .check span { display: block; font-size: 12px; color: #6d788c; margin-top: 2px; word-break: break-all; }
        .option { display: flex; gap: 10px; align-items: flex-start; padding: 11px 0; }
        .option input { margin-top: 3px; }
        .actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 22px; }
        button { border: 0; border-radius: 9px; background: #172033; color: white; font: inherit; font-weight: 700; padding: 12px 18px; cursor: pointer; }
        button:hover { background: #26344d; }
        code { background: #f0f2f5; border-radius: 5px; padding: 2px 5px; }
        @media (max-width: 760px) { .grid, .checks { grid-template-columns: 1fr; } main { margin-top: 24px; } .card { padding: 18px; } }
    </style>
</head>
<body>
<main>
    <h1>First-run deployment</h1>
    <p class="lead">Review the values discovered from this server, provide the credentials the application cannot discover, and let the installer prepare the central database, tenant database user, production environment and first Control Center administrator.</p>

    @if ($pending)
        <div class="alert alert-warn"><strong>Resuming an incomplete installation.</strong> Existing cPanel resources will be verified and reused where possible. Secret fields are intentionally not redisplayed.</div>
    @endif

    @if ($globalError)
        <div class="alert alert-error"><strong>Installation did not complete.</strong><br>{{ $globalError }}</div>
    @endif

    <section class="card">
        <h2>Server discovery</h2>
        <div class="checks">
            @foreach ($checks as $check)
                <div class="check">
                    <span class="dot {{ $check['ok'] ? 'ok' : 'bad' }}"></span>
                    <div><strong>{{ $check['label'] }}</strong><span>{{ $check['detail'] }}</span></div>
                </div>
            @endforeach
        </div>
        <p class="hint">Red checks should be corrected before installation. The installer performs a second authoritative cPanel and database validation when submitted.</p>
    </section>

    <form method="post" action="/install" autocomplete="off">
        <section class="card">
            <h2>Application</h2>
            <div class="grid">
                <div><label for="app_name">Application name</label><input id="app_name" name="app_name" type="text" value="{{ $values['app_name'] ?? '' }}" required>@foreach($errors['app_name'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="app_url">Application URL</label><input id="app_url" name="app_url" type="url" value="{{ $values['app_url'] ?? '' }}" required>@foreach($errors['app_url'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="central_domain">Control Center domain</label><input id="central_domain" name="central_domain" type="text" value="{{ $values['central_domain'] ?? '' }}" required><div class="hint">Must match the hostname currently serving this installer.</div>@foreach($errors['central_domain'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="platform_domain">Tenant platform root</label><input id="platform_domain" name="platform_domain" type="text" value="{{ $values['platform_domain'] ?? '' }}" required><div class="hint">Tenant URLs become <code>tenantid.&lt;platform-domain&gt;</code>.</div>@foreach($errors['platform_domain'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div class="full"><label for="document_root">Shared Laravel public document root</label><input id="document_root" name="document_root" type="text" value="{{ $values['document_root'] ?? '' }}" required>@foreach($errors['document_root'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div class="full"><label for="custom_domain_dns_target">Custom-domain DNS target</label><input id="custom_domain_dns_target" name="custom_domain_dns_target" type="text" value="{{ $values['custom_domain_dns_target'] ?? '' }}" required>@foreach($errors['custom_domain_dns_target'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
            </div>
        </section>

        <section class="card">
            <h2>cPanel API</h2>
            <div class="grid">
                <div><label for="cpanel_host">cPanel API host</label><input id="cpanel_host" name="cpanel_host" type="text" value="{{ $values['cpanel_host'] ?? '' }}" required><div class="hint">A public hostname with a trusted TLS certificate.</div>@foreach($errors['cpanel_host'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="cpanel_port">Secure API port</label><input id="cpanel_port" name="cpanel_port" type="number" value="{{ $values['cpanel_port'] ?? 2083 }}" required>@foreach($errors['cpanel_port'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="cpanel_user">cPanel account user</label><input id="cpanel_user" name="cpanel_user" type="text" value="{{ $values['cpanel_user'] ?? '' }}" required>@foreach($errors['cpanel_user'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="cpanel_token">cPanel API token</label><input id="cpanel_token" name="cpanel_token" type="password" value="" autocomplete="new-password" required><div class="hint">Never redisplayed. The token must manage the domain currently serving this installer.</div>@foreach($errors['cpanel_token'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
            </div>
        </section>

        <section class="card">
            <h2>Central database</h2>
            <div class="grid">
                <div><label for="db_host">Database host</label><input id="db_host" name="db_host" type="text" value="{{ $values['db_host'] ?? 'localhost' }}" required>@foreach($errors['db_host'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="db_port">Database port</label><input id="db_port" name="db_port" type="number" value="{{ $values['db_port'] ?? 3306 }}" required>@foreach($errors['db_port'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="central_db_name">Central database name</label><input id="central_db_name" name="central_db_name" type="text" value="{{ $values['central_db_name'] ?? '' }}" required>@foreach($errors['central_db_name'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="central_db_user">Central database user</label><input id="central_db_user" name="central_db_user" type="text" value="{{ $values['central_db_user'] ?? '' }}" required>@foreach($errors['central_db_user'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div class="full"><label for="central_db_password">Central database user password</label><input id="central_db_password" name="central_db_password" type="password" value="" autocomplete="new-password" required>@foreach($errors['central_db_password'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
            </div>
        </section>

        <section class="card">
            <h2>Tenant databases</h2>
            <div class="grid">
                <div><label for="tenant_db_host">Tenant database host</label><input id="tenant_db_host" name="tenant_db_host" type="text" value="{{ $values['tenant_db_host'] ?? 'localhost' }}" required>@foreach($errors['tenant_db_host'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="tenant_db_port">Tenant database port</label><input id="tenant_db_port" name="tenant_db_port" type="number" value="{{ $values['tenant_db_port'] ?? 3306 }}" required>@foreach($errors['tenant_db_port'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="tenant_db_user">Tenant application DB user</label><input id="tenant_db_user" name="tenant_db_user" type="text" value="{{ $values['tenant_db_user'] ?? '' }}" required><div class="hint">The application grants this user access to each tenant database as tenants are provisioned.</div>@foreach($errors['tenant_db_user'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="tenant_db_prefix">Tenant database prefix</label><input id="tenant_db_prefix" name="tenant_db_prefix" type="text" value="{{ $values['tenant_db_prefix'] ?? '' }}" required>@foreach($errors['tenant_db_prefix'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div class="full"><label for="tenant_db_password">Tenant application DB user password</label><input id="tenant_db_password" name="tenant_db_password" type="password" value="" autocomplete="new-password" required>@foreach($errors['tenant_db_password'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
            </div>
        </section>

        <section class="card">
            <h2>First Control Center administrator</h2>
            <div class="grid">
                <div><label for="admin_name">Name</label><input id="admin_name" name="admin_name" type="text" value="{{ $values['admin_name'] ?? '' }}" required>@foreach($errors['admin_name'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="admin_email">Email</label><input id="admin_email" name="admin_email" type="email" value="{{ $values['admin_email'] ?? '' }}" required>@foreach($errors['admin_email'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="admin_password">Password</label><input id="admin_password" name="admin_password" type="password" value="" autocomplete="new-password" required>@foreach($errors['admin_password'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach</div>
                <div><label for="admin_password_confirmation">Confirm password</label><input id="admin_password_confirmation" name="admin_password_confirmation" type="password" value="" autocomplete="new-password" required></div>
            </div>
        </section>

        <section class="card">
            <h2>Application options</h2>
            <label class="option"><input type="checkbox" name="registration" value="1" {{ ($values['registration'] ?? false) ? 'checked' : '' }}><span><strong>Enable public tenant-user registration</strong><br><span class="hint">Off is the safer default for business applications.</span></span></label>
            <label class="option"><input type="checkbox" name="verification" value="1" {{ ($values['verification'] ?? true) ? 'checked' : '' }}><span><strong>Require tenant-user email verification</strong><br><span class="hint">A real mail transport can be configured after installation; the starter initially keeps mail on the log driver.</span></span></label>
            <label class="option"><input type="checkbox" name="confirm_install" value="1" required><span><strong>I understand this will create or reuse cPanel database resources, write the production <code>.env</code>, run central migrations and create the first Control Center administrator.</strong></span></label>
            @foreach($errors['confirm_install'] ?? [] as $error)<div class="error">{{ $error }}</div>@endforeach

            <div class="actions">
                <span class="hint">After success, this installer locks itself and normal Control Center routing takes over.</span>
                <button type="submit">Install &amp; configure</button>
            </div>
        </section>
    </form>
</main>
</body>
</html>
