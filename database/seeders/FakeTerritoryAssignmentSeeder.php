<?php

namespace Database\Seeders;

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class FakeTerritoryAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $territories = Territory::all();

        $campaigns = Campaign::factory(3)->create();

        TerritoryAssignment::factory(3)
            // ->for($territories->random())
            // ->for($users->random(), 'assignee')
            // ->has($campaigns->random())
            ->state(fn () => [
                'assignee_id' => $users->random()->id,
                'territory_id' => $territories->random()->id,
                'campaign_id' => $campaigns->random()->id,
                // 'assigned_to' => $users->random()->id,
                'created_by' => $users->random()->id,
            ])->create();
    }
}
