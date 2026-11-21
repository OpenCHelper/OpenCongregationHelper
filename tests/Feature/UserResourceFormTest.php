<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

test('creating a user that can log in without a password emails them a link to set it', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'can_login' => true,
            'password' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-resources.user.set_password_link_sent', ['email' => 'new.user@example.com']));

    $user = User::query()->where('email', 'new.user@example.com')->sole();

    expect($user->password)->not->toBeNull();

    Notification::assertSentTo($user, SetPasswordNotification::class, function (SetPasswordNotification $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return Password::broker()->tokenExists($user, $notification->token)
            && str_starts_with($url, Filament::getPanel('app')->getUrl())
            && str_contains($url, urlencode($user->email));
    });
});

test('the link emailed to a new user lets them set their password', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'can_login' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'new.user@example.com')->sole();
    $token = Notification::sent($user, SetPasswordNotification::class)->sole()->token;

    auth()->logout();

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    expect(Hash::check('chosen-password', $user->refresh()->password))->toBeTrue();
});

test('the set password email is sent in the preferred language of the user', function () {
    $user = User::factory()->create(['locale' => 'it', 'can_login' => true]);

    SetPasswordNotification::sendTo($user);

    $email = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();

    expect($email->getSubject())->toBe('Il tuo account '.config('app.name'))
        ->and($email->getHtmlBody())->toContain('Imposta password');
});

test('creating a user with a password sets it and sends no email', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'can_login' => true,
            'password' => 'chosen-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'new.user@example.com')->sole();

    expect(Hash::check('chosen-password', $user->password))->toBeTrue();
    Notification::assertNothingSent();
});

test('creating a virtual user sends no email', function () {
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
