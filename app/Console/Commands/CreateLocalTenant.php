<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CentralSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateLocalTenant extends Command
{
    protected $signature = 'tenant:local-create
        {id? : Tenant ID, for example acme}
        {--name= : Organization name}
        {--domain= : Local hostname; defaults to <tenant-id>.localhost}
        {--admin-name= : Initial administrator name}
        {--admin-email= : Initial administrator email}
        {--admin-password= : Initial administrator password; omit to enter securely}';

    protected $description = 'Create a local SQLite tenant without calling cPanel';

    public function handle(CentralSettings $settings): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('tenant:local-create is available only in local or testing environments.');

            return self::FAILURE;
        }

        $driver = (string) config('database.connections.tenant_template.driver');
        if ($driver !== 'sqlite') {
            $this->components->error("Local tenant creation requires the tenant template driver to be sqlite; current driver is [{$driver}].");
            $this->line('Set TENANT_DB_DRIVER=sqlite for local development or remove the variable to use the starter local default.');

            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive();
        $id = strtolower(trim((string) ($this->argument('id') ?: ($interactive ? $this->ask('Tenant ID', 'acme') : ''))));
        $name = trim((string) ($this->option('name') ?: ($interactive ? $this->ask('Organization name', 'Acme Corporation') : '')));
        $domain = strtolower(trim((string) ($this->option('domain') ?: ($id !== '' ? $id.'.localhost' : ''))));
        $adminName = trim((string) ($this->option('admin-name') ?: ($interactive ? $this->ask('Administrator name') : '')));
        $adminEmail = strtolower(trim((string) ($this->option('admin-email') ?: ($interactive ? $this->ask('Administrator email') : ''))));
        $adminPassword = (string) ($this->option('admin-password') ?: ($interactive ? $this->secret('Administrator password') : ''));
        $minimumPasswordLength = $settings->passwordMinimumLength();

        $validated = Validator::make([
            'id' => $id,
            'name' => $name,
            'domain' => $domain,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
        ], [
            'id' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/'],
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}\\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:'.$minimumPasswordLength],
        ])->validate();

        if (Tenant::query()->whereKey($validated['id'])->exists()) {
            $this->components->error("Tenant [{$validated['id']}] already exists.");

            return self::FAILURE;
        }

        if (Domain::query()->where('domain', $validated['domain'])->exists()) {
            $this->components->error("Domain [{$validated['domain']}] is already registered.");

            return self::FAILURE;
        }

        $databaseName = 'tenant-'.$validated['id'].'.sqlite';
        $databasePath = database_path($databaseName);
        if (is_file($databasePath)) {
            $this->components->error("Local tenant database [{$databasePath}] already exists. Refusing to overwrite it.");

            return self::FAILURE;
        }

        $tenant = null;

        try {
            $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::create([
                'id' => $validated['id'],
                'name' => $validated['name'],
                'status' => 'provisioning',
                'provisioning_status' => 'database',
                'database_name' => $databaseName,
                'initial_admin_email' => $validated['admin_email'],
                'tenancy_db_name' => $databaseName,
            ]));

            if (! $tenant->database()->manager()->createDatabase($tenant)) {
                throw new \RuntimeException('Unable to create the local SQLite tenant database.');
            }

            $tenant->domains()->create([
                'domain' => $validated['domain'],
                'type' => 'platform',
                'status' => 'active',
                'is_primary' => true,
                'dns_verified_at' => now(),
                'cpanel_verified_at' => now(),
                'ssl_verified_at' => now(),
            ]);

            $tenant->update(['provisioning_status' => 'migrating']);
            $tenant->run(function (): void {
                $exit = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);

                if ($exit !== 0) {
                    throw new \RuntimeException(trim(Artisan::output()) ?: 'Tenant migration failed.');
                }
            });

            $tenant->update(['provisioning_status' => 'administrator']);
            $tenant->run(function () use ($validated): void {
                User::query()->create([
                    'name' => $validated['admin_name'],
                    'email' => $validated['admin_email'],
                    'password' => $validated['admin_password'],
                    'role' => UserRole::Administrator->value,
                    'email_verified_at' => Carbon::now(),
                ]);
            });

            $tenant->update([
                'status' => 'active',
                'provisioning_status' => 'active',
                'provisioning_error' => null,
                'provisioned_at' => now(),
                'suspended_at' => null,
            ]);
        } catch (Throwable $e) {
            if ($tenant instanceof Tenant) {
                $tenant->domains()->delete();
                Tenant::withoutEvents(fn (): bool => (bool) $tenant->delete());
            }

            foreach ([$databasePath, $databasePath.'-journal', $databasePath.'-wal', $databasePath.'-shm'] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            $this->components->error('Local tenant creation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Local tenant [{$validated['id']}] is ready.");
        $this->line('Tenant URL: http://'.$validated['domain'].':8000');
        $this->line('Administrator: '.$validated['admin_email']);

        return self::SUCCESS;
    }
}
