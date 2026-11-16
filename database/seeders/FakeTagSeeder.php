<?php

namespace Database\Seeders;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class FakeTagSeeder extends Seeder
{
    /**
     * The tags to create, by type, as a congregation would use them.
     *
     * The zones and territories seeded by `FakeTerritorySeeder` are tagged with these names.
     *
     * @var array<string, list<string>>
     */
    public const TAGS = [
        TagType::ZONES->value => [
            'Residential',
            'Suburban',
            'Commercial',
            'Mixed use',
            'Low density',
        ],
        TagType::TERRITORIES->value => [
            'Apartment buildings',
            'Single-family homes',
            'Intercom',
            'Gated',
            'Shops',
            'Do not call list',
            'Foreign language',
            'Elderly residents',
            'Car needed',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::TAGS as $type => $names) {
            foreach ($names as $name) {
                Tag::findOrCreate($name, $type);
            }
        }
    }
}
