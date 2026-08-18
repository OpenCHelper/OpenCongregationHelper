<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;

beforeEach(function () {
    // Ensure we're in a non-production environment for protection tests
    config(['app.env' => 'testing']);

    // Create root user directly in database with specific ID
    $this->rootUser = User::factory()->create(['id' => 1]);

    // Create regular user
    $this->regularUser = User::factory()->create(['id' => 2]);
});

describe('Profile Page Root User Protection', function () {
    it('prevents updating email for root user via profile form', function () {
        $this->actingAs($this->rootUser);

        $component = Livewire::test(UpdateProfileInformationForm::class)
            ->set('state.email', 'newemail@example.com')
            ->call('updateProfileInformation');

        // Email should not have changed
        expect($this->rootUser->fresh()->email)->toBe($this->rootUser->email);
    });

    it('allows updating non-protected fields for root user via profile form', function () {
        $this->actingAs($this->rootUser);

        $originalFirstName = $this->rootUser->first_name;

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state.first_name', 'NewFirstName')
            ->set('state.last_name', $this->rootUser->last_name)
            ->set('state.locale', $this->rootUser->locale)
            ->call('updateProfileInformation');

        // First name should have changed
        expect($this->rootUser->fresh()->first_name)->toBe('NewFirstName');
        expect($this->rootUser->fresh()->first_name)->not->toBe($originalFirstName);
    });

    it('allows updating all fields for regular user via profile form', function () {
        $this->actingAs($this->regularUser);

        $originalEmail = $this->regularUser->email;

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state.email', 'newemail@example.com')
            ->set('state.first_name', 'NewFirstName')
            ->set('state.last_name', $this->regularUser->last_name)
            ->set('state.locale', $this->regularUser->locale)
            ->call('updateProfileInformation');

        $updatedUser = $this->regularUser->fresh();
        expect($updatedUser->email)->toBe('newemail@example.com');
        expect($updatedUser->email)->not->toBe($originalEmail);
        expect($updatedUser->first_name)->toBe('NewFirstName');
    });

    it('prevents password update for root user via profile form', function () {
        $this->actingAs($this->rootUser);

        $component = Livewire::test(UpdatePasswordForm::class)
            ->set('state.current_password', 'password')
            ->set('state.password', 'new-password')
            ->set('state.password_confirmation', 'new-password')
            ->call('updatePassword');

        // Should have validation errors
        $component->assertHasErrors(['current_password']);
    });

    it('allows password update for regular user via profile form', function () {
        $this->actingAs($this->regularUser);

        Livewire::test(UpdatePasswordForm::class)
            ->set('state.current_password', 'password')
            ->set('state.password', 'new-password')
            ->set('state.password_confirmation', 'new-password')
            ->call('updatePassword');

        // Password should have been updated (verified by hash check)
        expect(Hash::check('new-password', $this->regularUser->fresh()->password))->toBeTrue();
    });

    it('prevents account deletion for root user via profile form', function () {
        $this->actingAs($this->rootUser);

        // The DeleteUser action should throw an exception
        expect(function () {
            Livewire::test(DeleteUserForm::class)
                ->set('password', 'password')
                ->call('deleteUser');
        })->toThrow(Exception::class, __('profile.root_user_protection.account_deletion_not_allowed'));

        // User should still exist
        expect($this->rootUser->fresh())->not->toBeNull();
    });

    it('allows account deletion for regular user via profile form', function () {
        $this->actingAs($this->regularUser);

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'password')
            ->call('deleteUser');

        // User should be deleted
        expect($this->regularUser->fresh())->toBeNull();
    });
});
