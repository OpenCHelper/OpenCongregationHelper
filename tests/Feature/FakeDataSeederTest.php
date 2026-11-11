<?php

use App\Enums\TagType;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Database\Seeders\FakeDataSeeder;
use Database\Seeders\FakeTagSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Tags\Tag;

test('the fake tag seeder creates at least one tag of each type', function () {
    $this->seed(FakeTagSeeder::class);

    expect(Tag::count())->toBe(8);

    foreach (TagType::cases() as $type) {
        expect(Tag::getWithType($type->value))->not->toBeEmpty();
    }
});

test('zone tags are created when attaching tags and none of their type exists', function () {
    Tag::factory()->count(3)->ofType(TagType::TERRITORIES)->create();

    $zone = TerritoryZone::factory()->withTags()->create();

    expect($zone->tagsWithType(TagType::ZONES->value))->toHaveCount(1)
        ->and(Tag::getWithType(TagType::ZONES->value))->toHaveCount(1);
});

test('territory tags are created when attaching tags and none of their type exists', function () {
    Tag::factory()->count(3)->ofType(TagType::ZONES)->create();

    $territory = Territory::factory()
        ->for(TerritoryZone::factory(), 'territoryZone')
        ->withTags()
        ->create();

    expect($territory->tagsWithType(TagType::TERRITORIES->value))->toHaveCount(1)
        ->and(Tag::getWithType(TagType::TERRITORIES->value))->toHaveCount(1);
});

test('existing tags of the requested type are reused when attaching tags', function () {
    $zoneTag = Tag::factory()->ofType(TagType::ZONES)->create();

    $zone = TerritoryZone::factory()->withTags()->create();

    expect($zone->tagsWithType(TagType::ZONES->value)->pluck('id')->all())->toBe([$zoneTag->id])
        ->and(Tag::getWithType(TagType::ZONES->value))->toHaveCount(1);
});

test('the fake data seeder seeds tagged zones and territories', function () {
    Notification::fake();

    // The root user (ID 1) is created by the DatabaseSeeder before seeding fake data
    User::factory()->create(['id' => 1]);

    $this->seed(FakeDataSeeder::class);

    expect(TerritoryZone::count())->toBe(3)
        ->and(TerritoryZone::with('tags')->get()->every(fn (TerritoryZone $zone): bool => $zone->tagsWithType(TagType::ZONES->value)->isNotEmpty()))->toBeTrue()
        ->and(Territory::withAnyTagsOfAnyType(Tag::getWithType(TagType::TERRITORIES->value))->exists())->toBeTrue();
});
