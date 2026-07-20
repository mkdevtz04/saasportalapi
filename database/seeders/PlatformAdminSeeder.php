<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        PlatformAdmin::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@trinetpay.online')],
            [
                'name'     => 'Platform Admin',
                'password' => env('ADMIN_PASSWORD', 'changeme123'),
            ]
        );

        $this->command->info('Platform admin created. Email: ' . env('ADMIN_EMAIL', 'admin@trinetpay.online'));
    }
}
