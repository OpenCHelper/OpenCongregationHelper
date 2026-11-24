<?php

use App\Filament\Guest\Pages\ViewSharedAssignment;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Models\SharedUrl;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create(['can_login' => true]);

    $this->assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create(['name' => 'Shared territory'])->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);
});

/**
 * Create a link for the assignment directly, as one left behind from before it could no longer be shared.
 */
function createLeftoverLink(TerritoryAssignment $assignment): SharedUrl
{
    return $assignment->shared()->create(['uid' => Str::uuid()->toString()]);
}

test('opening the share modal does not create a link', function () {
    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->mountAction('share')
        ->assertActionMounted('share')
        ->assertSchemaStateSet(['share_url' => null], 'mountedActionSchema0')
        ->assertMountedActionModalSee(__('filament-resources.territory_assignment.share_modal.not_shared'))
        ->assertActionVisible(TestAction::make('create_share_link')->schemaComponent('shareLinkActions', 'mountedActionSchema0'));

    expect(SharedUrl::query()->exists())->toBeFalse();
});

test('the share modal shows the existing link without creating another one', function () {
    $this->actingAs($this->assignee);
    $sharedUrl = $this->assignment->share();

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->mountAction('share')
        ->assertSchemaStateSet(['share_url' => $sharedUrl->getShareableUrl()], 'mountedActionSchema0')
        ->assertSchemaComponentHidden('shareLinkActions', 'mountedActionSchema0');

    expect($this->assignment->fresh()->share()->is($sharedUrl))->toBeTrue()
        ->and(SharedUrl::query()->count())->toBe(1);
});

test('an assignment has one link at most', function () {
    createLeftoverLink($this->assignment);

    expect(fn () => createLeftoverLink($this->assignment))->toThrow(UniqueConstraintViolationException::class);
});

test('stopping sharing deletes the link, and is offered only while there is one', function () {
    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionHidden(['share', 'stop_sharing']);

    $this->assignment->share();

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionVisible(['share', 'stop_sharing'])
        ->callAction(['share', 'stop_sharing']);

    expect(SharedUrl::query()->exists())->toBeFalse();
});

test('a returned assignment cannot be shared, and opening the share action creates no link', function () {
    $this->actingAs($this->assignee);
    $this->assignment->update(['ended_at' => now()]);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionHidden('share')
        ->mountAction('share')
        ->assertActionNotMounted('share');

    expect(SharedUrl::query()->exists())->toBeFalse()
        ->and(ViewTerritoryAssignment::canManageSharing($this->assignment->fresh()))->toBeFalse()
        ->and(fn () => $this->assignment->fresh()->share())->toThrow(AuthorizationException::class)
        ->and(SharedUrl::query()->exists())->toBeFalse();
});

test('a deleted assignment cannot be shared', function () {
    $this->assignment->delete();
    $trashedAssignment = TerritoryAssignment::withTrashed()->find($this->assignment->getKey());

    expect($trashedAssignment->isShareable())->toBeFalse()
        ->and(fn () => $trashedAssignment->share())->toThrow(AuthorizationException::class)
        ->and(SharedUrl::query()->exists())->toBeFalse();
});

test('the link of a returned assignment does not open, and is deleted', function (string $path) {
    $this->withoutVite();
    $this->assignment->update(['ended_at' => now()]);
    $sharedUrl = createLeftoverLink($this->assignment);

    $this->get(str_replace('{uid}', $sharedUrl->uid, $path))->assertNotFound();

    expect(SharedUrl::query()->exists())->toBeFalse();
})->with([
    'redirect' => '/share/{uid}',
    'assignment page' => '/guest/shared-assignment/{uid}',
    'generic page' => '/guest/shared-resource/{uid}',
]);

test('the shared page of an assignment returned meanwhile cannot be opened again', function () {
    $sharedUrl = $this->assignment->share();
    Filament::setCurrentPanel('guest');

    Livewire::test(ViewSharedAssignment::class, ['record' => $sharedUrl->uid])->assertOk();

    // Set without the observer, which deletes the link at the return: as for a link created after it
    TerritoryAssignment::withoutEvents(fn () => $this->assignment->update(['ended_at' => now()]));

    Livewire::test(ViewSharedAssignment::class, ['record' => $sharedUrl->uid])->assertNotFound();

    expect(SharedUrl::query()->exists())->toBeFalse();
});

test('the share modal texts are translated in every language of the application', function (string $locale) {
    foreach (['not_shared', 'create_link'] as $key) {
        expect(trans()->hasForLocale("filament-resources.territory_assignment.share_modal.$key", $locale))->toBeTrue();
    }
})->with(['en', 'es', 'it']);

test('a link can be deleted only by those who can view what it shares, and is never edited or restored', function () {
    $sharedUrl = $this->assignment->share();
    $stranger = User::factory()->create(['can_login' => true]);

    expect(Gate::forUser($this->assignee)->allows('delete', $sharedUrl))->toBeTrue()
        ->and(Gate::forUser($this->assignee)->allows('forceDelete', $sharedUrl))->toBeTrue()
        ->and(Gate::forUser($stranger)->allows('delete', $sharedUrl))->toBeFalse()
        ->and(Gate::forUser($this->assignee)->allows('update', $sharedUrl))->toBeFalse()
        ->and(Gate::forUser($this->assignee)->allows('restore', $sharedUrl))->toBeFalse();
});
