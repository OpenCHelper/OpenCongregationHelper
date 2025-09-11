<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create users directly in the database
    DB::table('users')->insert([
        'id' => 1,
        'first_name' => 'Root',
        'last_name' => 'User',
        'email' => 'root@example.com',
        'password' => bcrypt('password'),
        'can_login' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('users')->insert([
        'id' => 2,
        'first_name' => 'Regular',
        'last_name' => 'User',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'can_login' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->rootUser = User::find(1);
    $this->regularUser = User::find(2);
});

it('correctly identifies the root user', function () {
    expect($this->rootUser->isRootUser())->toBeTrue();
    expect($this->regularUser->isRootUser())->toBeFalse();
});

it('correctly identifies when root user is protected in non-production', function () {
    app()->detectEnvironment(fn () => 'local');
    
    expect($this->rootUser->isRootUserProtected())->toBeTrue();
    expect($this->regularUser->isRootUserProtected())->toBeFalse();
});

it('correctly identifies deletion permissions', function () {
    expect($this->rootUser->canBeDeleted())->toBeFalse();
    expect($this->regularUser->canBeDeleted())->toBeTrue();
});

it('does not protect root user in production environment', function () {
    app()->detectEnvironment(fn () => 'production');
    
    expect($this->rootUser->isRootUserProtected())->toBeFalse();
    expect($this->regularUser->isRootUserProtected())->toBeFalse();
});

it('prevents updating email for protected root user via observer', function () {
    app()->detectEnvironment(fn () => 'local');
    
    $originalEmail = $this->rootUser->email;
    
    $this->rootUser->update(['email' => 'newemail@example.com']);
    
    expect($this->rootUser->fresh()->email)->toBe($originalEmail);
});

it('prevents updating password for protected root user via observer', function () {
    app()->detectEnvironment(fn () => 'local');
    
    $originalPassword = $this->rootUser->password;
    
    $this->rootUser->update(['password' => 'newpassword123']);
    
    expect($this->rootUser->fresh()->password)->toBe($originalPassword);
});

it('prevents updating can_login for protected root user via observer', function () {
    app()->detectEnvironment(fn () => 'local');
    
    $originalCanLogin = $this->rootUser->can_login;
    
    $this->rootUser->update(['can_login' => false]);
    
    expect($this->rootUser->fresh()->can_login)->toBe($originalCanLogin);
});

it('allows updating other fields for protected root user', function () {
    app()->detectEnvironment(fn () => 'local');
    
    $this->rootUser->update([
        'first_name' => 'New First Name',
        'last_name' => 'New Last Name',
    ]);
    
    $updated = $this->rootUser->fresh();
    expect($updated->first_name)->toBe('New First Name');
    expect($updated->last_name)->toBe('New Last Name');
});

it('allows updating all fields for regular users', function () {
    app()->detectEnvironment(fn () => 'local');
    
    $this->regularUser->update([
        'email' => 'updated@example.com',
        'can_login' => false,
        'first_name' => 'Updated Name',
    ]);
    
    $updated = $this->regularUser->fresh();
    expect($updated->email)->toBe('updated@example.com');
    expect($updated->can_login)->toBe(0);
    expect($updated->first_name)->toBe('Updated Name');
});

it('prevents deletion of root user via observer', function () {
    // Root user deletion should be prevented in all environments now
    expect(fn() => $this->rootUser->delete())
        ->toThrow(Exception::class, __('profile.root_user_protection.account_deletion_not_allowed'));
    
    // The root user should still exist in the database
    expect(User::find(1))->not->toBeNull();
});

it('allows deletion of regular users', function () {
    app()->detectEnvironment(fn () => 'local');
    
    $regularUserId = $this->regularUser->id;
    
    $result = $this->regularUser->delete();
    
    // The delete should succeed
    expect($result)->toBeTrue();
    
    // The user should be deleted from the database
    expect(User::find($regularUserId))->toBeNull();
});

it('allows updating root user fields in production environment', function () {
    app()->detectEnvironment(fn () => 'production');
    
    $this->rootUser->update([
        'email' => 'production@example.com',
        'can_login' => false,
    ]);
    
    $updated = $this->rootUser->fresh();
    expect($updated->email)->toBe('production@example.com');
    expect($updated->can_login)->toBe(0);
});
