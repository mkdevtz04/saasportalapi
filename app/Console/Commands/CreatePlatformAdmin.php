<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates a platform administrator. Without --password a strong one is generated and shown
 * once. There is no default account and no default password anywhere in the platform.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'admin:create
        {email : Sign-in email}
        {--name=Platform Admin : Display name}
        {--password= : Leave out to generate a strong password}';

    protected $description = 'Create a platform administrator';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        if (PlatformAdmin::where('email', $email)->exists()) {
            $this->error("An administrator with {$email} already exists.");

            return self::FAILURE;
        }

        $password  = (string) $this->option('password');
        $generated = $password === '';

        if ($generated) {
            $password = Str::password(20, symbols: false);
        }

        if (strlen($password) < 12) {
            $this->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }

        PlatformAdmin::create([
            'name'     => (string) $this->option('name'),
            'email'    => $email,
            'password' => $password,
        ]);

        $this->info("Administrator created: {$email}");

        if ($generated) {
            $this->warn("Password (shown once, store it safely): {$password}");
        }

        return self::SUCCESS;
    }
}
