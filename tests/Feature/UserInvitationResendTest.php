<?php

use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    // The root user can log in without a password in some set-ups, but never gets invitations
    $this->rootUser = User::factory()->create(['id' => 1, 'can_login' => true, 'password' => null]);

    $this->manager = User::factory()->create(['can_login' => true]);
    foreach (['ViewAny:User', 'View:User', 'Update:User'] as $permission) {
        $this->manager->givePermissionTo(Permission::create(['name' => $permission]));
    }

    $this->invitedUser = User::factory()->create(['can_login' => true, 'password' => null]);
});

test('correcting the email of a user who has not chosen their password sends the invitation to the new address', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    $previousToken = Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER)->createToken($this->invitedUser);
    $previousUser = clone $this->invitedUser;

    Livewire::test(EditUser::class, ['record' => $this->invitedUser->id])
        ->assertSee(__('filament-resources.user.field_email_invitation_helper'))
        ->fillForm(['email' => 'corrected@example.com'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-resources.user.invitation_sent', ['email' => 'corrected@example.com']));

    $user = $this->invitedUser->refresh();

    expect($user->email)->toBe('corrected@example.com')
        ->and(Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER)->tokenExists($previousUser, $previousToken))->toBeFalse();

    Notification::assertSentTo($user, UserInvitationNotification::class, fn (UserInvitationNotification $notification): bool => Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER)->tokenExists($user, $notification->token));

    auth()->logout();

    // The previous link does not work for either address
    foreach (['previous', 'corrected'] as $email) {
        Livewire::withQueryParams(['invitation' => 1])
            ->test(ResetPassword::class, ['email' => $email === 'previous' ? $previousUser->email : $user->email, 'token' => $previousToken])
            ->fillForm(['password' => 'chosen-password', 'passwordConfirmation' => 'chosen-password'])
            ->call('resetPassword')
            ->assertNotified(__($email === 'previous' ? PasswordBroker::INVALID_USER : PasswordBroker::INVALID_TOKEN));
    }

    expect($user->refresh()->password)->toBeNull();
});

test('changing the email sends no invitation to users who have a password or cannot log in', function (string $kind) {
    Notification::fake();
    $this->actingAs($this->manager);

    $user = User::factory()->create($kind === 'with a password'
        ? ['can_login' => true, 'password' => 'original-password']
        : ['can_login' => false, 'password' => null]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->assertDontSee(__('filament-resources.user.field_email_invitation_helper'))
        ->fillForm(['email' => 'changed@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->email)->toBe('changed@example.com');
    Notification::assertNothingSent();
})->with(['with a password', 'without access']);

test('editing a user who has not chosen their password without changing the email sends no invitation', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->invitedUser->id])
        ->fillForm(['first_name' => 'Updated'])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertNothingSent();
});

test('the invitation can be resent only to users who can log in and have not chosen their password', function (string $kind, bool $isVisible) {
    $this->actingAs($this->manager);

    $user = match ($kind) {
        'awaiting the invitation' => $this->invitedUser,
        'with a password' => User::factory()->create(['can_login' => true]),
        'virtual' => User::factory()->create(['can_login' => false, 'password' => null]),
        'root' => $this->rootUser,
    };

    $assertion = $isVisible ? 'assertActionVisible' : 'assertActionHidden';

    Livewire::test(ViewUser::class, ['record' => $user->id])->{$assertion}('resendInvitation');
    Livewire::test(ListUsers::class)->{$assertion}(TestAction::make('resendInvitation')->table($user));

    if ($kind !== 'root') {
        Livewire::test(EditUser::class, ['record' => $user->id])->{$assertion}('resendInvitation');
    }
})->with([
    'awaiting the invitation' => ['awaiting the invitation', true],
    'with a password' => ['with a password', false],
    'virtual' => ['virtual', false],
    'root' => ['root', false],
]);

test('resending the invitation replaces the previous one', function (string $page) {
    Notification::fake();
    $this->actingAs($this->manager);

    $previousToken = Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER)->createToken($this->invitedUser);
    $this->travel(2)->minutes();

    $test = $page === 'list'
        ? Livewire::test(ListUsers::class)->callAction(TestAction::make('resendInvitation')->table($this->invitedUser))
        : Livewire::test(ViewUser::class, ['record' => $this->invitedUser->id])->callAction('resendInvitation');

    $test->assertNotified(__('filament-resources.user.invitation_sent', ['email' => $this->invitedUser->email]));

    $token = Notification::sent($this->invitedUser, UserInvitationNotification::class)->sole()->token;
    $broker = Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER);

    expect($broker->tokenExists($this->invitedUser, $token))->toBeTrue()
        ->and($broker->tokenExists($this->invitedUser, $previousToken))->toBeFalse();
})->with(['view', 'list']);

test('the invitation cannot be resent again before the throttle of the invitations has passed', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    $throttle = config('auth.passwords.'.ResetPassword::INVITATION_PASSWORD_BROKER.'.throttle');

    Livewire::test(ViewUser::class, ['record' => $this->invitedUser->id])
        ->callAction('resendInvitation')
        ->assertNotified(__('filament-resources.user.invitation_sent', ['email' => $this->invitedUser->email]));

    Livewire::test(ViewUser::class, ['record' => $this->invitedUser->id])
        ->callAction('resendInvitation')
        ->assertNotified(__('filament-resources.user.resend_invitation_throttled', ['seconds' => $throttle]));

    Notification::assertSentToTimes($this->invitedUser, UserInvitationNotification::class, 1);

    $this->travel($throttle + 1)->seconds();

    Livewire::test(ViewUser::class, ['record' => $this->invitedUser->id])
        ->callAction('resendInvitation')
        ->assertNotified(__('filament-resources.user.invitation_sent', ['email' => $this->invitedUser->email]));

    Notification::assertSentToTimes($this->invitedUser, UserInvitationNotification::class, 2);
});

test('users who cannot update users cannot resend invitations', function () {
    Notification::fake();

    $viewer = User::factory()->create(['can_login' => true]);
    foreach (['ViewAny:User', 'View:User'] as $permission) {
        $viewer->givePermissionTo(Permission::findByName($permission));
    }

    $this->actingAs($viewer);

    Livewire::test(ViewUser::class, ['record' => $this->invitedUser->id])
        ->assertActionHidden('resendInvitation')
        // Forced as a crafted Livewire request would do, bypassing the hidden button
        ->call('mountAction', 'resendInvitation')
        ->call('callMountedAction');

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('resendInvitation')->table($this->invitedUser))
        ->call('mountAction', 'resendInvitation', [], ['table' => true, 'recordKey' => $this->invitedUser->getKey()])
        ->call('callMountedAction');

    Notification::assertNothingSent();
});
