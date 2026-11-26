<?php

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
    Deleting a tag also removes it from every territory and zone that uses it: the confirmation says how many.
*/

beforeEach(function () {
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    foreach (['ViewAny:Tag', 'Delete:Tag', 'DeleteAny:Tag'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->zone = TerritoryZone::factory()->create();
    $this->tag = Tag::findOrCreate('Apartment blocks');
    $this->otherTag = Tag::findOrCreate('Shops');
});

test('the usage counts read naturally, also when one of them is zero', function (string $locale, string $expected) {
    app()->setLocale($locale);

    expect(__('filament-resources.tag.delete_description', [
        'territories' => trans_choice('filament-resources.tag.delete_usage_territories', 0),
        'zones' => trans_choice('filament-resources.tag.delete_usage_zones', 3),
    ]))->toStartWith($expected)
        ->and(trans_choice('filament-resources.tag.delete_usage_territories', 1))->not->toContain(':count');
})->with([
    'English' => ['en', 'This tag is used by 0 territories and 3 zones:'],
    'Italian' => ['it', 'Questo tag è usato da 0 territori e 3 zone:'],
    'Spanish' => ['es', 'Esta etiqueta la usan 0 territorios y 3 zonas:'],
]);

test('the confirmation of a deletion tells how many territories and zones use the tag', function () {
    Territory::factory()->count(2)->create(['territory_zone_id' => $this->zone->id])
        ->each->attachTag($this->tag);
    $this->zone->attachTag($this->tag);

    Livewire::test(ManageTags::class)
        ->mountAction(TestAction::make('delete')->table($this->tag))
        ->assertMountedActionModalSee(__('filament-resources.tag.delete_description', [
            'territories' => trans_choice('filament-resources.tag.delete_usage_territories', 2),
            'zones' => trans_choice('filament-resources.tag.delete_usage_zones', 1),
        ]));
});

test('the confirmation says when no territory or zone uses the tag', function () {
    Livewire::test(ManageTags::class)
        ->mountAction(TestAction::make('delete')->table($this->tag))
        ->assertMountedActionModalSee(__('filament-resources.tag.delete_description_unused'));
});

test('deleting a tag removes it from its territories and zones, which are kept', function () {
    $territory = Territory::factory()->create(['territory_zone_id' => $this->zone->id]);
    $territory->attachTags([$this->tag, $this->otherTag]);
    $this->zone->attachTag($this->tag);

    Livewire::test(ManageTags::class)
        ->callAction(TestAction::make('delete')->table($this->tag))
        ->assertHasNoFormErrors();

    expect(Tag::find($this->tag->id))->toBeNull()
        ->and($territory->fresh()->tags->pluck('id')->all())->toBe([$this->otherTag->id])
        ->and($this->zone->fresh()->tags)->toBeEmpty();
});

test('the confirmation of a bulk deletion counts each territory once', function () {
    // Uses both tags: counted once
    Territory::factory()->create(['territory_zone_id' => $this->zone->id])->attachTags([$this->tag, $this->otherTag]);
    Territory::factory()->create(['territory_zone_id' => $this->zone->id])->attachTag($this->otherTag);
    // Deleted territories are not counted
    tap(Territory::factory()->create(['territory_zone_id' => $this->zone->id]), function (Territory $territory): void {
        $territory->attachTag($this->tag);
        $territory->delete();
    });

    Livewire::test(ManageTags::class)
        ->selectTableRecords([$this->tag->id, $this->otherTag->id])
        ->mountAction(TestAction::make('delete')->table()->bulk())
        ->assertMountedActionModalSee(__('filament-resources.tag.bulk_delete_description', [
            'territories' => trans_choice('filament-resources.tag.delete_usage_territories', 2),
            'zones' => trans_choice('filament-resources.tag.delete_usage_zones', 0),
        ]));
});
