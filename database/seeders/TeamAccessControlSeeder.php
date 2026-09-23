<?php

namespace Database\Seeders;

use App\Services\Teams\TeamAccessControl;
use Illuminate\Database\Seeder;

/**
 * Seeds the team-module permission catalogue and the global Owner, Admin,
 * and Member roles. The admin panel assigns what each role can do; every
 * team account with that role follows the same grants.
 */
class TeamAccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app(TeamAccessControl::class)->ensureCatalogue();
    }
}
