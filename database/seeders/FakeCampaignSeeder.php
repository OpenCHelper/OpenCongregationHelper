<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seed the campaigns of a congregation.
 *
 * The dates are relative to the day of seeding, so that the demo, reset every night, always shows an active,
 * an expiring, a terminated and an upcoming campaign; the yearly campaigns are named after the year they start in.
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
        $specialCampaignFrom = now()->subWeeks(3)->startOfDay();
        $memorialFrom = now()->subMonths(8)->startOfDay();
        $conventionFrom = now()->addMonth()->startOfDay();

        return [
            [
                'name' => 'Letter writing',
                'short_name' => 'LTR',
                'description' => 'Letters to the residents of gated buildings and of the homes never found at home.',
                'color' => '#6a1b9a',
                'valid_from' => now()->subMonths(2)->startOfDay(),
                'valid_until' => now()->addMonths(2)->endOfDay(),
            ],
            [
                'name' => "Special campaign {$specialCampaignFrom->year}",
                'short_name' => 'SPC',
                'description' => 'Distribution of the special issue of the magazine to every home of the territory.',
                'color' => '#ef6c00',
                'valid_from' => $specialCampaignFrom,
                'valid_until' => now()->addDays(6)->endOfDay(),
            ],
            [
                'name' => "Memorial invitation {$memorialFrom->year}",
                'short_name' => 'MEM',
                'description' => 'Invitations to the Memorial of the death of Jesus: cover the whole territory in the three weeks before.',
                'color' => '#c62828',
                'valid_from' => $memorialFrom,
                'valid_until' => now()->subMonths(7)->endOfDay(),
            ],
            [
                'name' => "Convention invitation {$conventionFrom->year}",
                'short_name' => 'CON',
                'description' => 'Invitations to the regional convention, in the month before it starts.',
                'color' => '#00838f',
                'valid_from' => $conventionFrom,
                'valid_until' => now()->addMonths(2)->endOfDay(),
            ],
        ];
    }
}
