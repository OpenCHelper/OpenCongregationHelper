<?php

use App\Enums\CampaignStatus;
use App\Enums\TagType;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Database\Seeders\FakeCampaignSeeder;
use Database\Seeders\FakeDataSeeder;
use Database\Seeders\FakeRoleSeeder;
use Database\Seeders\FakeTagSeeder;
use Database\Seeders\FakeTerritorySeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Tags\Tag;

test('the fake tag seeder creates the named tags of each type', function () {
    $this->seed(FakeTagSeeder::class);

    expect(Tag::count())->toBe(collect(FakeTagSeeder::TAGS)->flatten()->count());

    foreach (TagType::cases() as $type) {
        expect(Tag::getWithType($type->value)->pluck('name')->sort()->values()->all())
            ->toBe(collect(FakeTagSeeder::TAGS[$type->value])->sort()->values()->all());
    }
});

test('seeding the fake tags twice does not duplicate them', function () {
    $this->seed(FakeTagSeeder::class);
    $this->seed(FakeTagSeeder::class);

    expect(Tag::count())->toBe(collect(FakeTagSeeder::TAGS)->flatten()->count());
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

    expect(TerritoryZone::count())->toBe(count(FakeTerritorySeeder::ZONES))
        ->and(TerritoryZone::pluck('name')->all())->toContain('Casalotti', 'Boccea', 'Primavalle')
        ->and(TerritoryZone::with('tags')->get()->every(fn (TerritoryZone $zone): bool => $zone->tagsWithType(TagType::ZONES->value)->isNotEmpty()))->toBeTrue()
        ->and(Territory::count())->toBe(collect(FakeTerritorySeeder::ZONES)->sum(fn (array $zone): int => count($zone['territories'])))
        ->and(Territory::with('tags')->get()->every(fn (Territory $territory): bool => $territory->tagsWithType(TagType::TERRITORIES->value)->isNotEmpty()))->toBeTrue()
        ->and(Territory::where('is_commercial', true)->exists())->toBeTrue()
        ->and(Tag::count())->toBe(collect(FakeTagSeeder::TAGS)->flatten()->count());
});

test('the fake territories are closed polygons that do not overlap', function () {
    User::factory()->create(['id' => 1]);

    $this->seed(FakeTerritorySeeder::class);

    $boxes = Territory::all()->map(function (Territory $territory): array {
        $ring = json_decode($territory->geojson, true)['features'][0]['geometry']['coordinates'][0];

        expect($ring)->toHaveCount(5)
            ->and($ring[0])->toBe($ring[4]);

        $longitudes = array_column($ring, 0);
        $latitudes = array_column($ring, 1);

        return [min($longitudes), max($longitudes), min($latitudes), max($latitudes)];
    });

    foreach ($boxes as $i => $a) {
        foreach ($boxes as $j => $b) {
            if ($i < $j) {
                $overlaps = $a[0] < $b[1] && $b[0] < $a[1] && $a[2] < $b[3] && $b[2] < $a[3];

                expect($overlaps)->toBeFalse();
            }
        }
    }
});

test('the fake campaigns cover every status', function () {
    Notification::fake();
    User::factory()->create(['id' => 1]);

    $this->seed(FakeCampaignSeeder::class);

    expect(Campaign::all()->map(fn (Campaign $campaign): CampaignStatus => $campaign->getStatus())->unique()->values()->all())
        ->toEqualCanonicalizing(CampaignStatus::cases())
        ->and(Campaign::where('valid_from', '>', now())->exists())->toBeTrue();
});

test('the fake assignments use started campaigns and one territory each', function () {
    Notification::fake();
    User::factory()->create(['id' => 1]);

    $this->seed(FakeDataSeeder::class);

    $assignments = TerritoryAssignment::with('campaign')->get();

    expect($assignments)->not->toBeEmpty()
        ->and($assignments->pluck('territory_id')->duplicates())->toBeEmpty()
        ->and($assignments->every(fn (TerritoryAssignment $assignment): bool => $assignment->campaign->valid_from === null || $assignment->campaign->valid_from->isPast()))->toBeTrue();
});

test('the fake role seeder gives a role to every user but the root one', function () {
    User::factory()->create(['id' => 1]);
    User::factory(8)->create();

    $this->seed(FakeRoleSeeder::class);

    expect(Role::pluck('name')->all())->toContain('territory_servant', 'service_overseer', 'group_overseer', 'publisher')
        ->and(User::find(1)->roles)->toBeEmpty()
        ->and(User::whereKeyNot(1)->get()->every(fn (User $user): bool => $user->roles->count() === 1))->toBeTrue()
        ->and(Role::findByName('publisher', 'web')->permissions->pluck('name')->all())->toContain('ViewAny:Territory', 'View:TerritoryGlobalMap')
        ->and(Role::findByName('publisher', 'web')->hasPermissionTo('Update:Territory'))->toBeFalse()
        ->and(Role::findByName('territory_servant', 'web')->hasPermissionTo('Create:Territory'))->toBeTrue()
        ->and(Permission::where('name', 'like', '%::%')->exists())->toBeFalse();
});
