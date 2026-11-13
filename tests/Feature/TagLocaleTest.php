<?php

use App\Enums\TagType;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Filament\Resources\TerritoryZones\Pages\EditTerritoryZone;
use App\Filament\Resources\TerritoryZones\Pages\ListTerritoryZones;
use App\Models\Tag;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    foreach ([
        'ViewAny:Territory', 'View:Territory', 'Update:Territory',
        'ViewAny:TerritoryZone', 'View:TerritoryZone', 'Update:TerritoryZone',
        'ViewAny:Tag', 'Create:Tag', 'Update:Tag',
    ] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->zone = TerritoryZone::factory()->create(['name' => 'North']);
});

/**
 * Create a tag of the given type the way it was stored under the English interface.
 */
function englishTag(string $name, TagType $type): Tag
{
    App::setLocale('en');

    return Tag::findOrCreate($name, $type->value);
}

test('saving a territory in another interface language keeps its tags', function () {
    $tag = englishTag('tag-reprehenderit', TagType::TERRITORIES);
    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['image_path' => null]);
    $territory->attachTag($tag);

    App::setLocale('it');

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->assertSet('data.tags', ['tag-reprehenderit'])
        ->fillForm(['notes' => 'Only the notes changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($territory->fresh()->tags->pluck('id')->all())->toBe([$tag->id])
        ->and(Tag::count())->toBe(1);
});

test('adding an existing tag to a territory in another interface language reuses it', function () {
    $tag = englishTag('tag-shared', TagType::TERRITORIES);
    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['image_path' => null]);

    App::setLocale('es');

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->fillForm(['tags' => ['tag-shared', 'tag-new']])
        ->call('save')
        ->assertHasNoFormErrors();

    $newTag = Tag::findFromString('tag-new', TagType::TERRITORIES->value);

    expect($territory->fresh()->tags->pluck('id')->sort()->values()->all())->toBe([$tag->id, $newTag->id])
        ->and(Tag::count())->toBe(2)
        ->and($newTag->getTranslations('name'))->toBe(['en' => 'tag-new']);
});

test('saving a zone in another interface language keeps its tags', function () {
    $tag = englishTag('tag-zone', TagType::ZONES);
    $this->zone->attachTag($tag);

    App::setLocale('it');

    Livewire::test(EditTerritoryZone::class, ['record' => $this->zone->getKey()])
        ->assertSet('data.tags', ['tag-zone'])
        ->fillForm(['name' => 'North East'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->zone->fresh()->tags->pluck('id')->all())->toBe([$tag->id])
        ->and(Tag::count())->toBe(1);
});

test('the territory tag filter finds tags in another interface language', function () {
    $tagged = Territory::factory()->for($this->zone, 'territoryZone')->create();
    $tagged->attachTag(englishTag('tag-filter', TagType::TERRITORIES));
    $untagged = Territory::factory()->for($this->zone, 'territoryZone')->create();

    App::setLocale('it');

    Livewire::test(ListTerritories::class)
        ->filterTable('tags', ['tag-filter'])
        ->assertCanSeeTableRecords([$tagged])
        ->assertCanNotSeeTableRecords([$untagged]);
});

test('the zone tag filter finds tags in another interface language', function () {
    $this->zone->attachTag(englishTag('tag-zone-filter', TagType::ZONES));
    $untagged = TerritoryZone::factory()->create(['name' => 'South']);

    App::setLocale('it');

    Livewire::test(ListTerritoryZones::class)
        ->filterTable('tags', ['tag-zone-filter'])
        ->assertCanSeeTableRecords([$this->zone])
        ->assertCanNotSeeTableRecords([$untagged]);
});

test('tags created from the tags page in another interface language are stored in the tags locale', function () {
    App::setLocale('it');

    Livewire::test(ManageTags::class)
        ->callAction(CreateAction::class, [
            'name' => ['en' => 'tag-created'],
            'slug' => ['en' => 'tag-created'],
            'type' => TagType::TERRITORIES->value,
        ])
        ->assertHasNoFormErrors();

    $tag = Tag::sole();

    expect($tag->getTranslations('name'))->toBe(['en' => 'tag-created'])
        ->and(Tag::findFromString('tag-created', TagType::TERRITORIES->value)?->is($tag))->toBeTrue();
});

test('the migration merges the tags that only differ by locale', function () {
    $insertTag = fn (array $name, ?string $type): int => DB::table('tags')->insertGetId([
        'name' => json_encode($name),
        'slug' => json_encode(array_map(fn (string $value): string => str($value)->slug()->toString(), $name)),
        'type' => $type,
    ]);

    $english = $insertTag(['en' => 'tag-x'], TagType::TERRITORIES->value);
    $italian = $insertTag(['it' => 'tag-x'], TagType::TERRITORIES->value);
    $spanish = $insertTag(['es' => 'tag-x'], TagType::TERRITORIES->value);
    $otherType = $insertTag(['it' => 'tag-x'], TagType::ZONES->value);
    $italianOnly = $insertTag(['it' => 'tag-y'], TagType::TERRITORIES->value);

    $both = Territory::factory()->for($this->zone, 'territoryZone')->create();
    $italianLinked = Territory::factory()->for($this->zone, 'territoryZone')->create();
    $spanishLinked = Territory::factory()->for($this->zone, 'territoryZone')->create();

    $link = fn (int $tagId, Territory $territory) => DB::table('taggables')->insert([
        'tag_id' => $tagId,
        'taggable_type' => $territory->getMorphClass(),
        'taggable_id' => $territory->getKey(),
    ]);
    $link($english, $both);
    $link($italian, $both);
    $link($italian, $italianLinked);
    $link($spanish, $spanishLinked);
    $link($spanish, $italianLinked);

    (require database_path('migrations/2026_11_13_161127_store_tag_names_in_a_single_locale.php'))->up();

    expect(DB::table('tags')->pluck('id')->sort()->values()->all())->toBe([$english, $otherType, $italianOnly])
        ->and(DB::table('taggables')->where('tag_id', $english)->count())->toBe(3)
        ->and($both->fresh()->tags->pluck('id')->all())->toBe([$english])
        ->and($italianLinked->fresh()->tags->pluck('id')->all())->toBe([$english])
        ->and($spanishLinked->fresh()->tags->pluck('id')->all())->toBe([$english])
        ->and(Tag::find($otherType)->getTranslations('name'))->toBe(['en' => 'tag-x', 'it' => 'tag-x'])
        ->and(Tag::find($italianOnly)->getTranslations('slug'))->toBe(['en' => 'tag-y', 'it' => 'tag-y']);
});
