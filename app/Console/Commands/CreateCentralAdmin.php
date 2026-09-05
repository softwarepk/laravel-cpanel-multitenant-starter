<?php

namespace App\Console\Commands;

use App\Models\CentralAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateCentralAdmin extends Command
{
    protected $signature = 'central:admin
        {--name= : Administrator name}
        {--email= : Administrator email}
        {--password= : Administrator password; omit to enter securely}';

    protected $description = 'Create or update a central control-plane administrator';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: ($this->input->isInteractive() ? $this->ask('Administrator name') : '')));
        $email = strtolower(trim((string) ($this->option('email') ?: ($this->input->isInteractive() ? $this->ask('Administrator email') : ''))));
        $password = (string) ($this->option('password') ?: ($this->input->isInteractive() ? $this->secret('Administrator password') : ''));

        $validated = Validator::make(['name' => $name, 'email' => $email, 'password' => $password], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ])->validate();

        $admin = CentralAdmin::query()->updateOrCreate(
            ['email' => $validated['email']],
            ['name' => $validated['name'], 'password' => $validated['password']],
        );

        $this->components->info('Central administrator ready: '.$admin->email);

        return self::SUCCESS;
    }
}
