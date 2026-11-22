<?php

use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\EditProfile;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use App\Settings\SecuritySettings;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->manager = User::factory()->create(['can_login' => true]);
    foreach (['ViewAny:User', 'View:User', 'Create:User', 'Update:User'] as $permission) {
        $this->manager->givePermissionTo(Permission::create(['name' => $permission]));
    }

    $this->superAdmin = User::factory()->create(['can_login' => true]);
    $this->superAdmin->assignRole($this->superAdminRole);

    $this->user = User::factory()->create([
        'can_login' => true,
        'password' => 'original-password',
    ]);
});

test('administrators cannot set the password of a user', function (string $page) {
    Notification::fake();
    $this->actingAs($this->manager);

    $test = $page === 'create'
        ? Livewire::test(CreateUser::class)->fillForm([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'can_login' => true,
        ])
        : Livewire::test(EditUser::class, ['record' => $this->user->id])->fillForm(['first_name' => 'Updated']);

    $test->assertSchemaComponentDoesNotExist('password', 'form')
        ->assertSchemaComponentDoesNotExist('password_setup', 'form')
        // A value forced into the form state, bypassing the interface, is ignored too
        ->set('data.password', 'forced-password')
        ->call($page === 'create' ? 'create' : 'save')
        ->assertHasNoFormErrors();

    if ($page === 'create') {
        expect(User::query()->where('email', 'new.user@example.com')->sole()->password)->toBeNull();
    } else {
        expect(Hash::check('original-password', $this->user->refresh()->password))->toBeTrue();
    }
})->with(['create', 'edit']);

/**
 * Fill the form to create a user that can log in.
 *
 * @param  array<string, mixed>  $data
 */
function createUserThatCanLogIn(array $data = []): Testable
{
    return Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'can_login' => true,
            ...$data,
        ])
        ->call('create');
}

test('creating a user that can log in sends them an invitation', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    Livewire::test(CreateUser::class)
        ->assertDontSee(__('filament-resources.user.field_can_login_invitation_helper'))
        ->fillForm(['can_login' => true])
        ->assertSee(__('filament-resources.user.field_can_login_invitation_helper'));

    createUserThatCanLogIn()
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-resources.user.invitation_sent', ['email' => 'new.user@example.com']));

    $user = User::query()->where('email', 'new.user@example.com')->sole();

    expect($user->password)->toBeNull();

    Notification::assertSentTo($user, UserInvitationNotification::class, function (UserInvitationNotification $notification) use ($user): bool {
        $request = Request::create($notification->toMail($user)->actionUrl);

        return Password::broker('invitations')->tokenExists($user, $notification->token)
            && ! Password::broker()->tokenExists($user, $notification->token)
            && URL::hasValidSignature($request)
            && $request->query('invitation') === '1';
    });
});

test('the invitation lets the user choose their password for 6 hours', function (int $hoursLater, bool $isValid) {
    Notification::fake();
    $this->actingAs($this->manager);

    createUserThatCanLogIn()->assertHasNoFormErrors();

    $user = User::query()->where('email', 'new.user@example.com')->sole();
    $token = Notification::sent($user, UserInvitationNotification::class)->sole()->token;

    auth()->logout();
    $this->travel($hoursLater)->hours();

    Livewire::withQueryParams(['invitation' => 1])
        ->test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
        ->call('resetPassword')
        ->assertNotified(__($isValid ? PasswordBroker::PASSWORD_RESET : PasswordBroker::INVALID_TOKEN));

    expect($user->refresh()->password !== null && Hash::check('chosen-password', $user->password))->toBe($isValid);
})->with([
    'right away' => [0, true],
    'after 5 hours' => [5, true],
    'after 7 hours' => [7, false],
]);

test('an unused invitation stops working once the password is set another way', function (string $setBy) {
    $user = User::factory()->create(['can_login' => true, 'password' => $setBy === 'profile' ? 'password' : null]);
    $invitationToken = Password::broker('invitations')->createToken($user);

    if ($setBy === 'reset') {
        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => Password::broker()->createToken($user)])
            ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
            ->call('resetPassword')
            ->assertNotified(__(PasswordBroker::PASSWORD_RESET));
    } else {
        $this->actingAs($user);

        Livewire::test(UpdatePasswordForm::class)
            ->set('state', [
                'current_password' => 'password',
                'password' => 'chosen-password',
                'password_confirmation' => 'chosen-password',
            ])
            ->call('updatePassword')
            ->assertHasNoErrors();

        auth()->logout();
    }

    expect(Password::broker('invitations')->tokenExists($user, $invitationToken))->toBeFalse();

    Livewire::withQueryParams(['invitation' => 1])
        ->test(ResetPassword::class, ['email' => $user->email, 'token' => $invitationToken])
        ->fillForm(['password' => 'invitation-password', 'passwordConfirmation' => 'invitation-password'])
        ->call('resetPassword')
        ->assertNotified(__(PasswordBroker::INVALID_TOKEN));

    expect(Hash::check('chosen-password', $user->refresh()->password))->toBeTrue();
})->with([
    'with "Forgot password?"' => 'reset',
    'from the profile page' => 'profile',
]);

test('the password reset page talks about choosing a password only when opened from an invitation', function (bool $isInvitation, array $texts, string $notification) {
    app()->setLocale('en');

    $user = User::factory()->create(['can_login' => true, 'password' => null]);
    $token = Password::broker($isInvitation ? 'invitations' : null)->createToken($user);

    Livewire::withQueryParams($isInvitation ? ['invitation' => 1] : [])
        ->test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->assertSeeInOrder($texts)
        ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
        ->call('resetPassword')
        ->assertNotified($notification);
})->with([
    'invitation' => [true, ['Choose your password', 'Set password'], 'Your password has been set. You can now sign in.'],
    'normal reset' => [false, ['Reset your password', 'Reset password'], 'Your password has been reset.'],
]);

test('an expired invitation still shows a translated error', function () {
    app()->setLocale('en');

    $user = User::factory()->create(['can_login' => true, 'password' => null]);
    $token = Password::broker('invitations')->createToken($user);

    $this->travel(7)->hours();

    Livewire::withQueryParams(['invitation' => 1])
        ->test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
        ->call('resetPassword')
        ->assertNotified('This password reset token is invalid.');
});

test('normal password resets still expire after an hour', function () {
    $token = Password::broker()->createToken($this->user);

    $this->travel(2)->hours();

    Livewire::test(ResetPassword::class, ['email' => $this->user->email, 'token' => $token])
        ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
        ->call('resetPassword')
        ->assertNotified(__(PasswordBroker::INVALID_TOKEN));

    expect(Hash::check('original-password', $this->user->refresh()->password))->toBeTrue();
});

test('invitation tokens are not accepted as normal password reset tokens', function () {
    $token = Password::broker('invitations')->createToken($this->user);

    Livewire::test(ResetPassword::class, ['email' => $this->user->email, 'token' => $token])
        ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
        ->call('resetPassword')
        ->assertNotified(__(PasswordBroker::INVALID_TOKEN));

    expect(Hash::check('original-password', $this->user->refresh()->password))->toBeTrue();
});

test('the invitation is sent in the preferred language of the user', function () {
    $user = User::factory()->create(['locale' => 'it', 'can_login' => true]);

    UserInvitationNotification::sendTo($user);

    $email = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();

    expect($email->getSubject())->toBe('Il tuo account '.config('app.name'))
        ->and($email->getHtmlBody())->toContain('Scegli la password')->toContain('6 ore');
});

test('creating a virtual user sends no invitation', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'can_login' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'new.user@example.com')->sole()->password)->toBeNull();
    Notification::assertNothingSent();
});

test('allowing access to a virtual user without a password sends them an invitation', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    $virtualUser = User::factory()->create(['can_login' => false, 'password' => null]);

    Livewire::test(EditUser::class, ['record' => $virtualUser->id])
        ->assertDontSee(__('filament-resources.user.field_can_login_invitation_helper'))
        ->fillForm(['can_login' => true])
        ->assertSee(__('filament-resources.user.field_can_login_invitation_helper'))
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-resources.user.invitation_sent', ['email' => $virtualUser->email]));

    expect($virtualUser->refresh()->can_login)->toBeTrue();
    Notification::assertSentTo($virtualUser, UserInvitationNotification::class);
});

test('editing a user who already has a password sends no invitation', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    $formerUser = User::factory()->create(['can_login' => false, 'password' => 'original-password']);

    foreach ([$this->user, $formerUser] as $user) {
        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['can_login' => true])
            ->assertDontSee(__('filament-resources.user.field_can_login_invitation_helper'))
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Hash::check('original-password', $user->refresh()->password))->toBeTrue();
    }

    Notification::assertNothingSent();
});

test('a user cannot be created with the email of another user', function () {
    $this->actingAs($this->manager);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => $this->user->email,
            'can_login' => false,
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);

    expect(User::query()->where('email', $this->user->email)->count())->toBe(1);
});

test('a user can be saved with their own email but not with the email of another user', function () {
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->user->id])
        ->fillForm(['first_name' => 'Updated'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->fillForm(['email' => $this->manager->email])
        ->call('save')
        ->assertHasFormErrors(['email' => 'unique']);

    expect($this->user->refresh()->email)->not->toBe($this->manager->email);
});

test('users that are not super administrators cannot assign roles', function () {
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->manager->id])
        ->assertFormFieldDisabled('roles')
        ->fillForm(['roles' => [$this->superAdminRole->id]])
        ->call('save');

    expect($this->manager->refresh()->hasRole('super_admin'))->toBeFalse();
});

test('super administrators can assign roles', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(EditUser::class, ['record' => $this->user->id])
        ->assertFormFieldEnabled('roles')
        ->fillForm(['roles' => [$this->editorRole->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->user->refresh()->hasRole('editor'))->toBeTrue();
});

test('an invited user can choose their password and set up two-factor authentication when the security settings require them', function () {
    Notification::fake();

    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    $settings = app(SecuritySettings::class);
    $settings->require_two_factor = true;
    $settings->require_email_verification = true;
    $settings->save();

    $this->actingAs($this->manager);
    createUserThatCanLogIn()->assertHasNoFormErrors();
    auth()->logout();

    $user = User::query()->where('email', 'new.user@example.com')->sole();
    $notification = Notification::sent($user, UserInvitationNotification::class)->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();

    // The invitation page is open to guests, whatever the requirements
    $this->get($notification->toMail($user)->actionUrl)->assertOk();

    Livewire::withQueryParams(['invitation' => 1])
        ->test(ResetPassword::class, ['email' => $user->email, 'token' => $notification->token])
        ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
        ->call('resetPassword')
        ->assertNotified(__(PasswordBroker::PASSWORD_RESET));

    // The invitation proved that the email belongs to the user, so only the two-factor set-up is left
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)
        ->get(EditProfile::getUrl())
        ->assertRedirect(Filament::getPanel('app')->getSetUpRequiredMultiFactorAuthenticationUrl());
});

test('a normal password reset does not confirm the email', function () {
    $user = User::factory()->create(['can_login' => true, 'email_verified_at' => null]);

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => Password::broker()->createToken($user)])
        ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
        ->call('resetPassword')
        ->assertNotified(__(PasswordBroker::PASSWORD_RESET));

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});
