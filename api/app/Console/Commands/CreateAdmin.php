<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {password} {--name=Admin}';

    protected $description = 'Create or reset a dashboard administrator';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $password = (string) $this->argument('password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        if (strlen($password) < 10) {
            $this->error('Use a password of at least 10 characters.');

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => (string) $this->option('name'), 'password' => $password]
        );

        $user->tokens()->delete();

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated').' administrator '.$email.'.');

        return self::SUCCESS;
    }
}
