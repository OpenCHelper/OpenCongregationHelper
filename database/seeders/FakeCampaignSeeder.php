<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seed the campaigns of a congregation.
 *
 * The dates are relative to the day of seeding, so that the demo, reset every night, always shows
 * campaigns in each status: without an end date, active, expiring, almost expired, terminated and upcoming.
 */
class FakeCampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($this->campaigns() as $campaign) {
            Campaign::factory()->create([
                ...$campaign,
                'created_by' => $users->random()->id,
            ]);
        }
    }

    /**
     * Get the campaigns to create.
     *
     * @return list<array{name: string, short_name: string, description: string, color: string, valid_from: ?Carbon, valid_until: ?Carbon}>
     */
    public function campaigns(): array
    {
        return [
            [
                'name' => 'House-to-house ministry',
                'short_name' => 'H2H',
                'description' => 'Regular door-to-door ministry, all year round.',
                'color' => '#1565c0',
                'valid_from' => null,
                'valid_until' => null,
            ],
            [
                'name' => 'Letter writing',
                'short_name' => 'LTR',
                'description' => 'Letters to the residents of gated buildings and of the homes never found at home.',
                'color' => '#6a1b9a',
                'valid_from' => now()->subMonths(2)->startOfDay(),
                'valid_until' => now()->addMonths(2)->endOfDay(),
            ],
            [
                'name' => 'Special campaign',
                'short_name' => 'SPC',
                'description' => 'Distribution of the special issue of the magazine to every home of the territory.',
                'color' => '#ef6c00',
                'valid_from' => now()->subWeeks(3)->startOfDay(),
                'valid_until' => now()->addDays(6)->endOfDay(),
            ],
            [
                'name' => 'Commercial territories',
                'short_name' => 'COM',
                'description' => 'Visits to shops and offices, on weekday mornings only.',
                'color' => '#2e7d32',
                'valid_from' => now()->subMonth()->startOfDay(),
                'valid_until' => now()->addDays(2)->endOfDay(),
            ],
            [
                'name' => 'Memorial invitation',
                'short_name' => 'MEM',
                'description' => 'Invitations to the Memorial of the death of Jesus: cover the whole territory in the three weeks before.',
                'color' => '#c62828',
                'valid_from' => now()->subMonths(8)->startOfDay(),
                'valid_until' => now()->subMonths(7)->endOfDay(),
            ],
            [
                'name' => 'Convention invitation',
                'short_name' => 'CON',
                'description' => 'Invitations to the regional convention, in the month before it starts.',
                'color' => '#00838f',
                'valid_from' => now()->addMonth()->startOfDay(),
                'valid_until' => now()->addMonths(2)->endOfDay(),
            ],
        ];
    }
}
