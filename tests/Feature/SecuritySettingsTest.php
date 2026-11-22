<?php

use App\Filament\Clusters\Settings\Pages\ManageSecuritySettings;
use App\Filament\Pages\EditProfile;
use App\Models\User;
use App\Settings\SecuritySettings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

/**
 * Change the Security settings.
 */
function setSecuritySettings(bool $requireTwoFactor = false, bool $requireEmailVerification = false): void
{
    $settings = app(SecuritySettings::class);
    $settings->require_two_factor = $requireTwoFactor;
    $settings->require_email_verification = $requireEmailVerification;
    $settings->save();
}

test('two-factor authentication and email confirmation are not required by default', function () {
    expect(SecuritySettings::value('require_two_factor'))->toBeFalse()
        ->and(SecuritySettings::value('require_email_verification'))->toBeFalse();
});

test('the security settings are stored from their page', function () {
    $superAdmin = User::factory()->create(['can_login' => true]);
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($superAdmin);

    Livewire::test(ManageSecuritySettings::class)
        ->assertSchemaStateSet(['require_two_factor' => false, 'require_email_verification' => false])
        ->fillForm(['require_two_factor' => true, 'require_email_verification' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(SecuritySettings::class)->refresh())
        ->require_two_factor->toBeTrue()
        ->require_email_verification->toBeTrue();
});

test('users with an unverified email can use the app when the email confirmation is not required', function () {
    setSecuritySettings(requireEmailVerification: false);

    $this->actingAs(User::factory()->create(['can_login' => true, 'email_verified_at' => null]))
        ->get(EditProfile::getUrl())
        ->assertOk();
});

test('users with an unverified email must confirm it when the email confirmation is required', function () {
    setSecuritySettings(requireEmailVerification: true);

    $this->actingAs(User::factory()->create(['can_login' => true, 'email_verified_at' => null]))
        ->get(EditProfile::getUrl())
        ->assertRedirect(Filament::getPanel('app')->getEmailVerificationPromptUrl());

    $this->actingAs(User::factory()->verified()->create(['can_login' => true]))
        ->get(EditProfile::getUrl())
        ->assertOk();
});

test('users without two-factor authentication can use the app when it is not required', function () {
    setSecuritySettings(requireTwoFactor: false);

    $this->actingAs(User::factory()->create(['can_login' => true]))
        ->get(EditProfile::getUrl())
        ->assertOk()
        // They can still enable it from their profile
        ->assertSee(__('filament-panels::auth/multi-factor/app/actions/set-up.label'));
});

test('users without two-factor authentication must set it up when it is required', function () {
    setSecuritySettings(requireTwoFactor: true);

    $this->actingAs(User::factory()->create(['can_login' => true]))
        ->get(EditProfile::getUrl())
        ->assertRedirect(Filament::getPanel('app')->getSetUpRequiredMultiFactorAuthenticationUrl());
});

test('users with two-factor authentication can use the app when it is required', function () {
    setSecuritySettings(requireTwoFactor: true);

    $user = User::factory()->create(['can_login' => true]);
    $user->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());

    $this->actingAs($user)
        ->get(EditProfile::getUrl())
        ->assertOk();
});

test('the confirmation email is sent once per session when the verification page opens', function () {
    Notification::fake();
    setSecuritySettings(requireEmailVerification: true);

    $user = User::factory()->create(['can_login' => true, 'email_verified_at' => null]);
    $promptUrl = Filament::getPanel('app')->getEmailVerificationPromptUrl();

    $this->actingAs($user)->get($promptUrl)->assertOk();
    $this->get($promptUrl)->assertOk();

    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('no confirmation email is sent to users whose email is already confirmed', function () {
    Notification::fake();
    setSecuritySettings(requireEmailVerification: true);

    $user = User::factory()->verified()->create(['can_login' => true]);

    $this->actingAs($user)
        ->get(Filament::getPanel('app')->getEmailVerificationPromptUrl())
        ->assertRedirect();

    Notification::assertNothingSentTo($user);
});
