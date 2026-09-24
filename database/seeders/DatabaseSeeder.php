<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * No login is in scope for this app (see BRIEF.md §Scope Boundaries),
     * so no User seeding is needed here.
     */
    public function run(): void
    {
        $this->call(ProductSeeder::class);
    }
}
