<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class FakeTerritoryAssignmentSeeder extends Seeder
{
    /**
     * The number of territories to assign, each one to a single user.
     */
    private const ASSIGNED_TERRITORIES_COUNT = 12;

    /**
     * The notes left on the assignments, as a publisher would write them.
     */
    private const NOTES = [
        null,
        null,
        'Worked the odd numbers, the even ones are still to do.',
        'Nobody at home in the morning: better to go back in the afternoon.',
        'Left the tracts in the mailboxes of the gated buildings.',
        'Two return visits to make, see the card.',
        'Worked together with the group on Saturday.',
    ];

    /**
     * Run the database seeds.
     *
     * Each territory gets at most one assignment, so that it is never assigned to two users at
     * once, and only the campaigns already started and not terminated (see `FakeCampaignSeeder`) are used: a
     * terminated campaign gets no new assignments.
     */
    public function run(): void
    {
        $users = User::all();
        $campaigns = Campaign::query()
            ->withActive()
            ->get();

        if ($campaigns->isEmpty()) {
            $campaigns = Campaign::factory(3)->create();
        }

        $territories = Territory::all()->shuffle()->take(self::ASSIGNED_TERRITORIES_COUNT);

        foreach ($territories as $territory) {
            TerritoryAssignment::factory()->create([
                'assignee_id' => $users->random()->id,
                'territory_id' => $territory->id,
                'campaign_id' => $campaigns->random()->id,
                'notes' => fake()->randomElement(self::NOTES),
                'created_by' => $users->random()->id,
            ]);
        }
    }
}
