<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class FakeDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info("Seeding the database with fake data (for testing)...");

        $this->call([
            FakeUserSeeder::class,
            FakeTagSeeder::class,
            FakeTerritorySeeder::class,
            FakeTerritoryAssignmentSeeder::class,
        ]);
    }
}
