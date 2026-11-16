<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production runs this on every start (migrate --seed), so only the roles are
     * seeded there. The example admin is for local development only.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        if (app()->isLocal()) {
            $this->call(AdminSeeder::class);
        }
    }
}
