<?php

namespace Database\Seeders;

use App\Enums\TagType;
use Illuminate\Database\Seeder;
use Spatie\Tags\Tag;

class FakeTagSeeder extends Seeder
{
    /**
     * The total number of fake tags to create.
     */
    private const TAGS_COUNT = 8;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create one tag of each type first, so that the factories attaching
        // random tags of a given type always find at least one (see #123).
        foreach (TagType::cases() as $type) {
            Tag::factory()->ofType($type)->create();
        }

        Tag::factory()
            ->count(max(0, self::TAGS_COUNT - count(TagType::cases())))
            ->create();
    }
}
