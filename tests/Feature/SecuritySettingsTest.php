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
    expect(app(SecuritySettings::class))
        ->require_two_factor->toBeFalse()
        ->require_email_verification->toBeFalse();
});

/**
 * Create the root user (ID 1), with or without two-factor authentication and a confirmed email.
 */
function createRootUser(bool $withTwoFactor = true, bool $withVerifiedEmail = true): User
{
    $root = User::factory()->create([
        'id' => 1,
        'can_login' => true,
        'email_verified_at' => $withVerifiedEmail ? now() : null,
    ]);

    if ($withTwoFactor) {
        $root->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());
    }

    return $root;
}

/**
 * Log in as a super administrator other than the root user.
 */
function actingAsSecuritySuperAdmin(): User
{
    $superAdmin = User::factory()->create(['can_login' => true]);
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($superAdmin);

    return $superAdmin;
}

test('the security settings are stored from their page', function () {
    createRootUser();
    actingAsSecuritySuperAdmin();

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
    createRootUser(); // So that the users below are not the root user, never blocked

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

test('two-factor authentication cannot be required while the root user has not set it up', function () {
    createRootUser(withTwoFactor: false);
    actingAsSecuritySuperAdmin();

    Livewire::test(ManageSecuritySettings::class)
        ->fillForm(['require_two_factor' => true])
        ->call('save')
        ->assertHasFormErrors(['require_two_factor'])
        ->assertSee(__('settings.fields.require_two_factor.root_required'));

    expect(app(SecuritySettings::class)->refresh()->require_two_factor)->toBeFalse();
});

test('email confirmation cannot be required while the root user has not confirmed its email', function () {
    createRootUser(withVerifiedEmail: false);
    actingAsSecuritySuperAdmin();

    Livewire::test(ManageSecuritySettings::class)
        ->fillForm(['require_email_verification' => true])
        ->call('save')
        ->assertHasFormErrors(['require_email_verification'])
        ->assertSee(__('settings.fields.require_email_verification.root_required'));

    expect(app(SecuritySettings::class)->refresh()->require_email_verification)->toBeFalse();
});

test('the requirements can always be turned off, whatever the root user has', function () {
    createRootUser(withTwoFactor: false, withVerifiedEmail: false);
    setSecuritySettings(requireTwoFactor: true, requireEmailVerification: true);
    actingAsSecuritySuperAdmin();

    Livewire::test(ManageSecuritySettings::class)
        ->fillForm(['require_two_factor' => false, 'require_email_verification' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(SecuritySettings::class)->refresh())
        ->require_two_factor->toBeFalse()
        ->require_email_verification->toBeFalse();
});

test('the root user is never blocked by the email confirmation requirement', function () {
    setSecuritySettings(requireEmailVerification: true);

    // E.g. after changing its email while the requirement was already on
    $root = createRootUser(withVerifiedEmail: false);
    $root->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

    $this->actingAs($root)
        ->get(ManageSecuritySettings::getUrl())
        ->assertOk();
});
