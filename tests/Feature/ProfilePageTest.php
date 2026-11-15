<?php

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Laravel\Jetstream\Http\Livewire\LogoutOtherBrowserSessionsForm;
use Laravel\Jetstream\Http\Livewire\TwoFactorAuthenticationForm;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;

beforeEach(function () {
    // The user with ID 1 is the root user, whose email and password cannot be changed
    $this->rootUser = User::factory()->create(['id' => 1]);

    Filament::setCurrentPanel('app');

    // Render the real assets when they are built (as in CI), and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

test('the profile page is made of Filament sections, not of the components of Jetstream', function () {
    $this->actingAs(User::factory()->create(['can_login' => true, 'email_verified_at' => now()]))
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSeeInOrder([
            __('profile.update_profile_information.title'),
            __('profile.contacts.section_title'),
            __('profile.update_password.title'),
            __('profile.browser_sessions.title'),
            __('profile.delete_account.title'),
        ])
        ->assertSee('fi-aside', escape: false)
        // The table of the contacts is already a card, so its section must not add another one around it
        ->assertSee('fi-section-not-contained', escape: false)
        // Layout of `<x-form-section>` and `<x-action-section>`, and style of `<x-button>`
        ->assertDontSee('md:grid md:grid-cols-3', escape: false)
        ->assertDontSee('uppercase tracking-widest', escape: false);
});

test('the title of the profile page is in the language of the user', function (string $locale, string $title) {
    $this->actingAs(User::factory()->create(['can_login' => true, 'email_verified_at' => now()]))
        ->withSession(['locale' => $locale])
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['<h1 class="fi-header-heading">', $title, '</h1>'], escape: false)
        ->assertDontSee('Edit Profile');
})->with([
    'English' => ['en', 'Profile'],
    'Italian' => ['it', 'Profilo'],
    'Spanish' => ['es', 'Perfil'],
]);

test('the root user is told why the protected fields cannot be changed', function () {
    $this->actingAs($this->rootUser);

    Livewire::test(UpdateProfileInformationForm::class)
        ->assertSeeHtml('fi-callout')
        ->assertSee(__('profile.root_user_protection.generic_field_disabled'));

    Livewire::test(UpdatePasswordForm::class)
        ->assertSeeHtml('fi-callout')
        ->assertSee(__('profile.root_user_protection.password_change_not_allowed'))
        ->assertDontSee(__('profile.update_password.current_password'));

    Livewire::test(DeleteUserForm::class)
        ->assertSeeHtml('fi-callout')
        ->assertSee(__('profile.root_user_protection.account_deletion_not_allowed'))
        ->assertDontSeeHtml('id="confirm-user-deletion"');
});

test('the confirmations of the profile page are Filament modals', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(DeleteUserForm::class)
        ->assertSeeHtml('id="confirm-user-deletion"')
        ->assertSeeHtml('fi-modal');

    Livewire::test(LogoutOtherBrowserSessionsForm::class)
        ->assertSeeHtml('id="confirm-logout-other-browser-sessions"')
        ->assertSeeHtml('fi-modal');
});

test('the browser sessions of the user are listed', function () {
    config(['session.driver' => 'database']);

    $this->actingAs($user = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]));

    DB::table('sessions')->insert([
        'id' => 'other-session',
        'user_id' => $user->id,
        'ip_address' => '192.0.2.10',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        'payload' => '',
        'last_activity' => now()->subHour()->getTimestamp(),
    ]);

    // Through the page, as the sessions are compared with the current one
    $this->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSee('192.0.2.10')
        ->assertSee('Windows')
        ->assertSee(__('profile.browser_sessions.last_active'));
});

test('the two factor authentication section can be rendered in every step', function () {
    config(['fortify.features' => [
        ...config('fortify.features'),
        Features::twoFactorAuthentication(['confirmPassword' => true]),
    ]]);

    $this->actingAs(User::factory()->create());
    $this->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(TwoFactorAuthenticationForm::class)
        ->assertSee(__('profile.two_factor_authentication.not_enabled'))
        ->assertSeeHtml('id="confirm-password"')
        ->call('enableTwoFactorAuthentication')
        ->assertSee(__('profile.two_factor_authentication.enabled'))
        ->assertSee(__('profile.two_factor_authentication.setup_key'))
        ->assertSee(__('profile.two_factor_authentication.recovery_codes_description'))
        // The step shown when the code must be confirmed (`two_factor_confirmed_at` exists only when that is enabled)
        ->set('showingConfirmation', true)
        ->assertSee(__('profile.two_factor_authentication.finish_enabling'))
        ->assertSeeHtml('autocomplete="one-time-code"');
});

test('the user is sent back to the profile page of the panel after uploading a photo', function () {
    Storage::fake('public');

    $this->actingAs($user = User::factory()->create());

    // Jetstream redirects to its own profile page when a photo is uploaded
    Livewire::test(UpdateProfileInformationForm::class)
        ->set('photo', UploadedFile::fake()->image('photo.jpg'))
        ->call('updateProfileInformation')
        ->assertRedirect(route('profile.show'));

    expect($user->fresh()->profile_photo_path)->not->toBeNull();

    $this->get(route('profile.show'))
        ->assertRedirect(EditProfile::getUrl());
});

test('the photo of the profile is the avatar of the user in the panel', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    // Without a photo, Filament generates the avatar from the initials
    expect($user->getFilamentAvatarUrl())->toBeNull();

    $user->updateProfilePhoto(UploadedFile::fake()->image('photo.jpg'));

    expect($user->getFilamentAvatarUrl())->toBe($user->profile_photo_url)
        ->and(filament()->getUserAvatarUrl($user))->toEndWith($user->profile_photo_path);
});
