<?php

use App\Enums\PasswordSetup;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
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

test('editing a user without typing a password keeps the current password', function () {
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->user->id])
        ->fillForm(['first_name' => 'Updated'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->user->refresh();

    expect($this->user->first_name)->toBe('Updated')
        ->and(Hash::check('original-password', $this->user->password))->toBeTrue();
});

test('editing a user with a new password updates it', function () {
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->user->id])
        ->fillForm(['password' => 'new-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-password', $this->user->refresh()->password))->toBeTrue();
});

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

test('creating a user that can log in sends them an invitation by default', function () {
    Notification::fake();
    $this->actingAs($this->manager);

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
    $user = User::factory()->create(['can_login' => true, 'password' => null]);
    $invitationToken = Password::broker('invitations')->createToken($user);

    if ($setBy === 'user') {
        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => Password::broker()->createToken($user)])
            ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
            ->call('resetPassword')
            ->assertNotified(__(PasswordBroker::PASSWORD_RESET));
    } else {
        $this->actingAs($this->manager);

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['password' => 'chosen-password'])
            ->call('save')
            ->assertHasNoFormErrors();

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
    'by the user with "Forgot password?"' => 'user',
    'by an administrator' => 'administrator',
]);

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

test('administrators can set the password of a new user instead of sending an invitation', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    Livewire::test(CreateUser::class)
        ->fillForm(['can_login' => true])
        ->assertSchemaComponentHidden('password', 'form')
        ->fillForm(['password_setup' => PasswordSetup::Password])
        ->assertSchemaComponentVisible('password', 'form');

    createUserThatCanLogIn(['password_setup' => PasswordSetup::Password, 'password' => null])
        ->assertHasFormErrors(['password' => 'required']);

    createUserThatCanLogIn(['password_setup' => PasswordSetup::Password, 'password' => 'chosen-password'])
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'new.user@example.com')->sole();

    expect(Hash::check('chosen-password', $user->password))->toBeTrue();
    Notification::assertNothingSent();
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
        ->assertSchemaComponentHidden('password_setup', 'form')
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
        ->assertSchemaComponentHidden('password_setup', 'form')
        ->fillForm(['can_login' => true])
        ->assertSchemaComponentVisible('password_setup', 'form')
        ->assertSchemaComponentHidden('password', 'form')
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
            ->assertSchemaComponentHidden('password_setup', 'form')
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Hash::check('original-password', $user->refresh()->password))->toBeTrue();
    }

    Notification::assertNothingSent();
});

test('passwords set by administrators follow the password policy', function () {
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->user->id])
        ->fillForm(['password' => 'short'])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect(Hash::check('original-password', $this->user->refresh()->password))->toBeTrue();
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
