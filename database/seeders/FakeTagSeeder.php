<?php

namespace Database\Seeders;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
                self::findOrCreate($name, TagType::from($type));
            }
        }
    }

    /**
     * Find the tag with the given name and type, or create it.
     *
     * The slug is set explicitly, since Spatie sets it in a model event, and the fake data
     * seeders run without model events (see `WithoutModelEvents`).
     */
    public static function findOrCreate(string $name, TagType $type): Tag
    {
        return Tag::findFromString($name, $type->value)
            ?? Tag::factory()->ofType($type)->create([
                'name' => [Tag::getLocale() => $name],
                'slug' => [Tag::getLocale() => Str::slug($name)],
            ]);
    }
}
