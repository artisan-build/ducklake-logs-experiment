<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'logs:create-admin {email} {--name=Admin}';

    protected $description = 'Create or rotate an operator login and print its one-time password.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('A valid email address is required.');

            return self::FAILURE;
        }

        $password = Str::password(24);
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => (string) $this->option('name'),
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();

        $this->line(json_encode([
            'email' => $user->email,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
