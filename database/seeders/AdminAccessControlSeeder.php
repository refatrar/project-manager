<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Seeds one default admin account so a fresh `migrate --seed` has a
 * working way into `/admin`. Platform admins are not assigned roles.
 */
class AdminAccessControlSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Platform Admin', 'password' => 'password'],
        );
    }
}
