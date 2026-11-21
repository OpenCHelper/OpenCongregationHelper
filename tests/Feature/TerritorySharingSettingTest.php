<?php

use App\Filament\Clusters\Settings\Pages\ManageTerritorySettings;
use App\Filament\Guest\Pages\ViewSharedAssignment;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Models\SharedUrl;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Settings\TerritorySettings;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

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
 * Enable or disable the sharing of the assignments from the territory settings.
 */
function setSharingEnabled(bool $enabled): void
{
    $settings = app(TerritorySettings::class);
    $settings->sharing_enabled = $enabled;
    $settings->save();
}

/**
 * Log in as a super administrator, who passes every gate.
 */
function actingAsSharingSuperAdmin(): User
{
    $superAdmin = User::factory()->create(['can_login' => true]);
    $superAdmin->assignRole(Role::findOrCreate('super_admin', 'web'));

    test()->actingAs($superAdmin);

    return $superAdmin;
}

test('sharing is enabled by default', function () {
    expect(TerritorySettings::value('sharing_enabled'))->toBeTrue()
        ->and(SharedUrl::isSharingEnabled())->toBeTrue();
});

test('the assignee can share an assignment while sharing is enabled', function () {
    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionVisible('share')
        ->mountAction('share')
        ->assertActionMounted('share');

    expect($this->assignment->fresh()->shared)->not->toBeNull()
        ->and(Gate::forUser($this->assignee)->allows('create', SharedUrl::class))->toBeTrue();
});

test('no assignment can be shared while sharing is disabled', function () {
    setSharingEnabled(false);
    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionHidden('share');

    expect(ViewTerritoryAssignment::canManageSharing($this->assignment))->toBeFalse()
        ->and(Gate::forUser($this->assignee)->allows('create', SharedUrl::class))->toBeFalse()
        ->and(fn () => $this->assignment->share())->toThrow(AuthorizationException::class)
        ->and($this->assignment->fresh()->shared)->toBeNull();
});

test('not even the super administrators can share an assignment while sharing is disabled', function () {
    setSharingEnabled(false);
    actingAsSharingSuperAdmin();

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionHidden('share');

    expect($this->assignment->fresh()->shared)->toBeNull();
});

test('the shared links work while sharing is enabled', function () {
    $this->withoutVite();
    $sharedUrl = $this->assignment->share();

    $this->get(route('share.redirect', $sharedUrl->uid))
        ->assertRedirect('/guest/shared-assignment/'.$sharedUrl->uid);

    $this->get('/guest/shared-assignment/'.$sharedUrl->uid)
        ->assertOk()
        ->assertSee('Shared territory');

    $this->get('/guest/shared-resource/'.$sharedUrl->uid)
        ->assertOk();
});

test('disabling sharing deletes the shared links, which do not work again once it is enabled', function () {
    $this->withoutVite();
    $sharedUrl = $this->assignment->share();
    $otherSharedUrl = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ])->share();

    setSharingEnabled(false);

    expect(SharedUrl::query()->count())->toBe(0)
        ->and($this->assignment->fresh()->shared)->toBeNull()
        ->and(TerritoryAssignment::query()->count())->toBe(2);

    $this->get(route('share.redirect', $sharedUrl->uid))->assertNotFound();
    $this->get('/guest/shared-assignment/'.$sharedUrl->uid)->assertNotFound();
    $this->get('/guest/shared-resource/'.$otherSharedUrl->uid)->assertNotFound();

    setSharingEnabled(true);

    $this->get(route('share.redirect', $sharedUrl->uid))->assertNotFound();
    $this->get('/guest/shared-assignment/'.$sharedUrl->uid)->assertNotFound();
});

test('the shared links are kept while sharing stays enabled', function () {
    $sharedUrl = $this->assignment->share();

    setSharingEnabled(true);

    expect(SharedUrl::whereKey($sharedUrl->getKey())->exists())->toBeTrue();
});

test('the shared links of a page opened before sharing was disabled do not work', function () {
    $this->withoutVite();
    $sharedUrl = $this->assignment->share();

    // As if the setting were changed while the link is followed: the pages refuse it before looking it up
    $settings = app(TerritorySettings::class);
    $settings->sharing_enabled = false;

    expect(Gate::forUser(null)->allows('view', $sharedUrl))->toBeFalse();
    $this->get(route('share.redirect', $sharedUrl->uid))->assertNotFound();
    $this->get('/guest/shared-assignment/'.$sharedUrl->uid)->assertNotFound();
});

test('the return cannot be asked from a shared page opened before sharing was disabled', function () {
    config(['app.guest_allow_returning_assignment' => true]);
    $sharedUrl = $this->assignment->share();
    Filament::setCurrentPanel('guest');

    $component = Livewire::test(ViewSharedAssignment::class, ['record' => $sharedUrl->uid])
        ->assertActionVisible('requestReturn');

    setSharingEnabled(false);

    $component->assertActionHidden('requestReturn');

    expect($this->assignment->fresh()->return_requested_at)->toBeNull();
});

test('super administrators can disable and enable sharing from the territory settings', function () {
    actingAsSharingSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->assertSee(__('settings.fields.sharing_enabled.label'))
        ->assertSchemaStateSet(['sharing_enabled' => true])
        ->fillForm(['sharing_enabled' => false])
        ->assertActionNotMounted('confirmDisableSharing')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(TerritorySettings::class)->sharing_enabled)->toBeFalse()
        ->and(SharedUrl::isSharingEnabled())->toBeFalse();

    Livewire::test(ManageTerritorySettings::class)
        ->assertSchemaStateSet(['sharing_enabled' => false])
        ->fillForm(['sharing_enabled' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SharedUrl::isSharingEnabled())->toBeTrue();
});

test('disabling sharing while links exist must be confirmed, and then deletes them on save', function () {
    actingAsSharingSuperAdmin();
    $this->assignment->share();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm(['sharing_enabled' => false])
        ->assertActionMounted('confirmDisableSharing')
        // Still enabled until confirmed
        ->assertSet('data.sharing_enabled', true)
        ->assertMountedActionModalSee(trans_choice('settings.fields.sharing_enabled.confirm_description', 1, ['count' => 1]))
        ->callMountedAction()
        ->assertSet('data.sharing_enabled', false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SharedUrl::isSharingEnabled())->toBeFalse()
        ->and(SharedUrl::query()->exists())->toBeFalse();
});

test('the shared links are kept when disabling sharing is cancelled', function () {
    actingAsSharingSuperAdmin();
    $this->assignment->share();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm(['sharing_enabled' => false])
        ->assertActionMounted('confirmDisableSharing')
        ->unmountAction()
        ->assertSet('data.sharing_enabled', true)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SharedUrl::isSharingEnabled())->toBeTrue()
        ->and(SharedUrl::query()->count())->toBe(1);
});

test('the setting is labelled in every language of the application', function (string $locale) {
    foreach (['label', 'helper', 'enabled', 'disabled', 'confirm_heading', 'confirm_description', 'confirm'] as $key) {
        expect(trans()->hasForLocale("settings.fields.sharing_enabled.$key", $locale))->toBeTrue();
    }
})->with(['en', 'es', 'it']);

test('the options of the shared pages are shown only while sharing is enabled', function () {
    actingAsSharingSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->assertSchemaComponentVisible('shared_pii', 'form')
        ->assertSchemaComponentVisible('guest_returns', 'form')
        ->fillForm(['sharing_enabled' => false])
        ->assertSchemaComponentHidden('shared_pii', 'form')
        ->assertSchemaComponentHidden('guest_returns', 'form')
        ->fillForm(['sharing_enabled' => true])
        ->assertSchemaComponentVisible('shared_pii', 'form')
        ->assertSchemaComponentVisible('guest_returns', 'form');
});

test('the options of the shared pages are kept while sharing is disabled', function () {
    actingAsSharingSuperAdmin();
    $settings = app(TerritorySettings::class);
    $settings->shared_pii = true;
    $settings->guest_returns = true;
    $settings->save();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm(['sharing_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(TerritorySettings::class)->refresh();

    expect($settings->sharing_enabled)->toBeFalse()
        ->and($settings->shared_pii)->toBeTrue()
        ->and($settings->guest_returns)->toBeTrue();

    Livewire::test(ManageTerritorySettings::class)
        ->assertSchemaComponentHidden('shared_pii', 'form')
        ->fillForm(['sharing_enabled' => true])
        ->assertSchemaStateSet(['shared_pii' => 'show', 'guest_returns' => true]);
});
