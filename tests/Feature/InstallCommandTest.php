<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config(['app.initial_root_user' => [
        'name' => 'Mario Rossi',
        'email' => 'root@example.com',
        'password' => 'secret-password',
    ]]);
});

test('the root user is created from the configuration as a super admin', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    $root = User::sole();

    expect($root->isRootUser())->toBeTrue()
        ->and($root->first_name)->toBe('Mario')
        ->and($root->last_name)->toBe('Rossi')
        ->and($root->email)->toBe('root@example.com')
        ->and($root->can_login)->toBeTrue()
        ->and(Hash::check('secret-password', $root->password))->toBeTrue()
        ->and($root->hasRole('super_admin'))->toBeTrue()
        ->and(Permission::where('name', 'ViewAny:Territory')->exists())->toBeTrue();
});

test('running it again changes nothing', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();
    $this->artisan('och:install', ['--no-interaction' => true])
        ->expectsOutputToContain('already a super admin')
        ->assertSuccessful();

    expect(User::count())->toBe(1)
        ->and(User::rootUser()->roles)->toHaveCount(1);
});

test('the existing root user is made a super admin when nobody is one', function () {
    $root = User::factory()->create(['id' => 1]);

    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    expect(User::count())->toBe(1)
        ->and($root->fresh()->hasRole('super_admin'))->toBeTrue();
});

test('the root user is not made a super admin again when another user is one', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    // E.g. in production, where the admins can change the roles of the root user
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    User::rootUser()->removeRole('super_admin');

    $this->artisan('och:install', ['--no-interaction' => true])
        ->expectsOutputToContain('already a super admin')
        ->assertSuccessful();

    expect(User::rootUser()->fresh()->hasRole('super_admin'))->toBeFalse();
});

test('it fails without prompting when the root user is not configured', function () {
    config(['app.initial_root_user.email' => null]);

    $this->artisan('och:install', ['--no-interaction' => true])
        ->expectsOutputToContain('INITIAL_ROOT_USERMAIL')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('it asks for the missing values when run interactively', function () {
    config(['app.initial_root_user.email' => null, 'app.initial_root_user.password' => null]);

    $this->artisan('och:install')
        ->expectsQuestion('Email address of the root user', 'admin@example.com')
        ->expectsQuestion('Password of the root user', 'another-password')
        ->assertSuccessful();

    $root = User::sole();

    expect($root->email)->toBe('admin@example.com')
        ->and($root->first_name)->toBe('Mario')
        ->and(Hash::check('another-password', $root->password))->toBeTrue()
        ->and($root->hasRole('super_admin'))->toBeTrue();
});

test('the root user gets ID 1 even when the next ID is higher', function () {
    User::factory()->create(['id' => 5])->delete();

    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    expect(User::sole()->isRootUser())->toBeTrue();
});
