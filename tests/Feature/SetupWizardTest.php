<?php

use App\Filament\Clusters\Settings\Pages\ManageGeneralSettings;
use App\Filament\Pages\SetupWizard;
use App\Models\SharedUrl;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\TestEmailNotification;
use App\Notifications\UserInvitationNotification;
use App\Settings\GeneralSettings;
use App\Settings\SecuritySettings;
use App\Settings\TerritorySettings;
use App\Support\AppName;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    $this->withoutVite();

    $this->root = User::factory()->create(['id' => 1, 'can_login' => true]);
    $this->root->assignRole(Role::findOrCreate('super_admin', 'web'));
});

/**
 * Mark the first-run setup as completed (or not).
 */
function setSetupCompleted(bool $completed): void
{
    $settings = app(GeneralSettings::class);
    $settings->setup_completed = $completed;
    $settings->save();
}

/**
 * Simulate the root user logging in, which offers the wizard on the first page (see `OfferSetupWizardAfterLogin`).
 */
function logInAsRoot(User $root): void
{
    test()->actingAs($root);
    event(new Login('web', $root, false));
}

test('a new installation is not set up, an existing one is', function () {
    expect(app(GeneralSettings::class)->setup_completed)->toBeFalse();

    // Run the settings migration again on a database that already has users
    DB::table('settings')->where('group', 'general')->whereIn('name', ['app_name', 'setup_completed'])->delete();
    config(['app.name' => 'Existing congregation']);
    (require database_path('settings/2026_11_26_120000_add_app_name_and_setup_settings.php'))->up();

    $stored = DB::table('settings')->where('group', 'general')->pluck('payload', 'name');

    expect(json_decode($stored['setup_completed']))->toBeTrue()
        ->and(json_decode($stored['app_name']))->toBe('Existing congregation');
});

test('only the root user can open the wizard, until the setup is completed', function () {
    $this->actingAs($this->root);
    Livewire::test(SetupWizard::class)->assertOk();

    $this->actingAs(User::factory()->create(['can_login' => true])->assignRole('super_admin'));
    Livewire::test(SetupWizard::class)->assertForbidden();

    setSetupCompleted(true);
    $this->actingAs($this->root);
    Livewire::test(SetupWizard::class)->assertForbidden();
});

test('the wizard opens instead of the first page after the root user logs in', function () {
    logInAsRoot($this->root);

    $this->get(Filament::getUrl())->assertRedirect(SetupWizard::getUrl());

    // The offers made after logging in wait for the next page, where the root user is reminded of the setup instead
    $this->get(SetupWizard::getUrl())
        ->assertOk()
        ->assertDontSee('offerAfterLogin: true', escape: false);

    $this->get(Filament::getUrl())
        ->assertOk()
        ->assertSee(__('setup.banner.text'))
        ->assertSee('offerAfterLogin: true', escape: false);
});

test('the wizard is not offered once the setup is completed, nor to other users', function () {
    setSetupCompleted(true);
    logInAsRoot($this->root);

    $this->get(Filament::getUrl())
        ->assertOk()
        ->assertDontSee(__('setup.banner.text'));

    setSetupCompleted(false);
    $admin = User::factory()->create(['can_login' => true])->assignRole('super_admin');
    $this->actingAs($admin);
    event(new Login('web', $admin, false));

    $this->get(Filament::getUrl())
        ->assertOk()
        ->assertDontSee(__('setup.banner.text'));
});

test('the wizard shows the checks of the installation', function () {
    $this->actingAs($this->root);

    Livewire::test(SetupWizard::class)
        ->assertSee(__('installation.checks.app_key.label'))
        ->assertSee(__('installation.checks.root_user.label'))
        ->assertSee(__('installation.checks.root_two_factor.failed'));
});

test('a test email can be sent to the root user', function () {
    Notification::fake();
    $this->actingAs($this->root);

    Livewire::test(SetupWizard::class)
        ->callAction(TestAction::make('sendTestEmail')->schemaComponent('installationActions', 'form'))
        ->assertNotified(__('setup.checks.test_email.sent', ['email' => $this->root->email]));

    Notification::assertSentTo($this->root, TestEmailNotification::class);
});

test('the test email is really sent', function () {
    $this->actingAs($this->root);

    Livewire::test(SetupWizard::class)
        ->callAction(TestAction::make('sendTestEmail')->schemaComponent('installationActions', 'form'))
        ->assertNotified(__('setup.checks.test_email.sent', ['email' => $this->root->email]));

    expect(DB::table('mail_logs')->where('to', 'like', "%{$this->root->email}%")->count())->toBe(1);
});

test('the failed checks that the app can fix offer to fix them', function () {
    $this->actingAs($this->root);

    $component = Livewire::test(SetupWizard::class);

    is_link(public_path('storage'))
        ? $component->assertActionHidden(TestAction::make('createStorageLink')->schemaComponent('installationActions', 'form'))
        : $component->assertActionVisible(TestAction::make('createStorageLink')->schemaComponent('installationActions', 'form'));
});

test('completing the wizard saves the settings and invites the administrators', function () {
    Notification::fake();
    $this->actingAs($this->root);

    Livewire::test(SetupWizard::class)
        ->fillForm([
            'app_name' => ' Congregation North ',
            'kingdom_hall_address' => 'Main Street 1',
            'primary_color' => '#2563eb',
            'administrators' => [
                ['first_name' => 'Anna', 'last_name' => 'Rossi', 'email' => 'anna@example.com'],
                ['first_name' => 'Marco', 'last_name' => 'Bianchi', 'email' => 'marco@example.com'],
            ],
            'ai_assistants_enabled' => true,
            'sharing_enabled' => true,
            'guest_returns' => true,
            'require_two_factor' => false,
            'require_email_verification' => false,
        ])
        ->call('complete')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    $general = app(GeneralSettings::class)->refresh();
    $security = app(SecuritySettings::class)->refresh();
    $territories = app(TerritorySettings::class)->refresh();

    expect($general->app_name)->toBe('Congregation North')
        ->and($general->kingdom_hall_address)->toBe('Main Street 1')
        ->and($general->primary_color)->toBe('#2563eb')
        ->and($general->setup_completed)->toBeTrue()
        ->and($security->ai_assistants_enabled)->toBeTrue()
        ->and($territories->guest_returns)->toBeTrue()
        ->and(config('app.name'))->toBe('Congregation North');

    foreach (['anna@example.com', 'marco@example.com'] as $email) {
        $administrator = User::query()->where('email', $email)->firstOrFail();

        expect($administrator->can_login)->toBeTrue()
            ->and($administrator->hasRole('super_admin'))->toBeTrue()
            ->and($administrator->password)->toBeNull();

        Notification::assertSentTo($administrator, UserInvitationNotification::class);
    }
});

test('the administrators must have a new and distinct email address', function () {
    $this->actingAs($this->root);
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(SetupWizard::class)
        ->fillForm([
            'administrators' => [
                ['first_name' => 'Anna', 'last_name' => 'Rossi', 'email' => 'taken@example.com'],
            ],
        ])
        ->call('complete')
        ->assertHasFormErrors(['administrators.0.email' => 'unique']);

    Livewire::test(SetupWizard::class)
        ->fillForm([
            'administrators' => [
                ['first_name' => 'Anna', 'last_name' => 'Rossi', 'email' => 'same@example.com'],
                ['first_name' => 'Marco', 'last_name' => 'Bianchi', 'email' => 'same@example.com'],
            ],
        ])
        ->call('complete')
        ->assertHasFormErrors(['administrators.0.email']);

    expect(app(GeneralSettings::class)->refresh()->setup_completed)->toBeFalse();
});

test('the requirements cannot lock out the root user', function () {
    $this->actingAs($this->root);

    Livewire::test(SetupWizard::class)
        ->fillForm(['require_two_factor' => true])
        ->call('complete')
        ->assertHasFormErrors(['require_two_factor']);
});

test('disabling the sharing deletes the shared links', function () {
    $this->actingAs($this->root);

    TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => $this->root->id,
        'ended_at' => null,
    ])->share();

    Livewire::test(SetupWizard::class)
        ->fillForm(['sharing_enabled' => false])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(SharedUrl::query()->count())->toBe(0)
        ->and(app(TerritorySettings::class)->refresh()->sharing_enabled)->toBeFalse();
});

test('the setup can be skipped', function () {
    $this->actingAs($this->root);

    Livewire::test(SetupWizard::class)
        ->callAction('skip')
        ->assertRedirect(Filament::getUrl());

    expect(app(GeneralSettings::class)->refresh()->setup_completed)->toBeTrue();
});

test('the name of the application comes from the settings', function () {
    $this->actingAs($this->root);
    config(['app.name' => 'From APP_NAME']);

    AppName::apply();
    expect(config('app.name'))->toBe(app(GeneralSettings::class)->app_name);

    Livewire::test(ManageGeneralSettings::class)
        ->assertSchemaStateSet(['app_name' => app(GeneralSettings::class)->app_name])
        ->fillForm(['app_name' => 'Renamed congregation'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GeneralSettings::class)->refresh()->app_name)->toBe('Renamed congregation')
        ->and(config('app.name'))->toBe('Renamed congregation');

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['app_name' => ''])
        ->call('save')
        ->assertHasFormErrors(['app_name' => 'required']);
});
