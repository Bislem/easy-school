<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'super-admin:create {--name=} {--email=} {--password=}';

    protected $description = 'Create a global Easy School super administrator';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = strtolower((string) ($this->option('email') ?: $this->ask('Email')));
        $password = $this->option('password') ?: $this->secret('Password');

        if (! $name || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 8) {
            $this->error('A valid name, email, and password of at least 8 characters are required.');

            return self::FAILURE;
        }

        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->error('This email is already in use.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name, 'email' => $email, 'password' => $password,
            'role' => UserRole::SUPER_ADMIN, 'tenant_id' => null,
            'is_active' => true, 'can_login' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->info('Super administrator created.');

        return self::SUCCESS;
    }
}
