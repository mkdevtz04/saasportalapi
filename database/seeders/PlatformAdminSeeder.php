<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;

/**
 * Creates the first platform administrator.
 *
 * On a local or testing machine it works out of the box: admin@trinetpay.online with the password
 * changeme123, unless ADMIN_EMAIL and ADMIN_PASSWORD say otherwise.
 *
 * On any other environment there is deliberately no default account. ADMIN_EMAIL and an
 * ADMIN_PASSWORD of at least 12 characters must be set, or nothing is created, because a known
 * default password on a live server would hand the money panel to anyone. The command
 * "php artisan admin:create you@example.com" does the same job and can generate a strong password.
 */
class PlatformAdminSeeder extends Seeder
{
    public const LOCAL_EMAIL    = 'admin@trinetpay.online';
    public const LOCAL_PASSWORD = 'changeme123';

    public function run(): void
    {
        $local    = app()->environment('local', 'testing');
        $email    = strtolower(trim((string) env('ADMIN_EMAIL', $local ? self::LOCAL_EMAIL : '')));
        $password = (string) env('ADMIN_PASSWORD', $local ? self::LOCAL_PASSWORD : '');

        if ($email === '' || ($local ? $password === '' : strlen($password) < 12)) {
            $this->command->error('Set ADMIN_EMAIL and an ADMIN_PASSWORD of at least 12 characters, or run: php artisan admin:create you@example.com');

            return;
        }

        PlatformAdmin::firstOrCreate(
            ['email' => $email],
            ['name' => 'Platform Admin', 'password' => $password]
        );

        $this->command->info("Platform admin ready: {$email}");

        if ($local && $password === self::LOCAL_PASSWORD) {
            $this->command->warn('Local development password in use (' . self::LOCAL_PASSWORD . '). It is refused on any other environment.');
        }
    }
}
