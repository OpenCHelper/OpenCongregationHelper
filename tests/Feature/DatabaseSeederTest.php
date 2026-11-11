<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('the root user is created from the configuration', function () {
    config(['app.initial_root_user' => [
        'name' => 'Mario Rossi',
        'email' => 'root@example.com',
        'password' => 'secret-password',
    ]]);

    $this->seed(DatabaseSeeder::class);

    $root = User::sole();

    expect($root->first_name)->toBe('Mario')
        ->and($root->last_name)->toBe('Rossi')
        ->and($root->email)->toBe('root@example.com')
        ->and($root->can_login)->toBeTrue()
        ->and(Hash::check('secret-password', $root->password))->toBeTrue();
});

test('the root user is not created when users already exist', function () {
    User::factory()->create();

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1);
});

test('seeding fails when the root user is not configured', function () {
    config(['app.initial_root_user' => ['name' => 'Root', 'email' => null, 'password' => 'secret']]);

    $this->seed(DatabaseSeeder::class);
})->throws(RuntimeException::class, 'INITIAL_ROOT_USERMAIL');
