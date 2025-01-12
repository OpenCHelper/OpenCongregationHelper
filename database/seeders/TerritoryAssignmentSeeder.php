<?php

namespace Database\Seeders;

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryAssignmentType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class TerritoryAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $territories = Territory::all();

        $territoryAssignmentTypes = TerritoryAssignmentType::factory(3)->create();

        TerritoryAssignment::factory(3)
            // ->for($territories->random())
            // ->for($users->random(), 'assignee')
            // ->has($territoryAssignmentTypes->random())
            ->state(fn () => [
                'assignee_id' => $users->random()->id,
                'territory_id' => $territories->random()->id,
                'territory_assignment_type_id' => $territoryAssignmentTypes->random()->id,
                // 'assigned_to' => $users->random()->id,
                'created_by' => $users->random()->id,
            ])->create();
    }
}
