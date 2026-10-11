<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the demo data. Model events stay on: the owners'
     * running accounts follow expenses and sales through them.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
