<?php

use App\Enums\DepartmentKey;
use App\Models\Department;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Database\Seeders\E2eSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // `och:install` creates the keys of the AI assistants when they are missing
    usePassportTestKeys();
    Notification::fake();

    $this->artisan('och:install')->assertSuccessful();
    $this->seed(E2eSeeder::class);
});

test('the users of the end-to-end tests have their role and the known password', function () {
    foreach (E2eSeeder::USERS as $email => $data) {
        $user = User::query()->where('email', $email)->sole();

        expect($user->first_name)->toBe($data['first_name'])
            ->and($user->can_login)->toBeTrue()
            ->and($user->hasVerifiedEmail())->toBeTrue()
            ->and($user->locale)->toBe('en')
            ->and(Hash::check(E2eSeeder::PASSWORD, $user->password))->toBeTrue()
            ->and($user->roles->pluck('name')->all())->toBe(array_filter([$data['role']]));
    }
});

test('the root user keeps being the only root user', function () {
    expect(User::rootUser()->email)->toBe(config('app.initial_root_user.email'));
});

test('the territory servant is the overseer of the territories department', function () {
    $servant = User::query()->where('email', 'servant@e2e.test')->sole();

    expect(Department::for(DepartmentKey::Territories)->members->pluck('id'))->toContain($servant->id);
});

test('the known territories are free, assigned and overdue', function () {
    $publisher = User::query()->where('email', 'publisher@e2e.test')->sole();
    $territory = fn (string $name): Territory => Territory::query()->where('name', $name)->sole();

    expect($territory('E2E Free')->assignments()->exists())->toBeFalse()
        ->and($territory('E2E Commercial')->is_commercial)->toBeTrue()
        ->and($territory('E2E Assigned')->assignments()->sole()->assignee_id)->toBe($publisher->id)
        ->and(TerritoryAssignment::query()->overdue()->pluck('territory_id'))->toContain($territory('E2E Overdue')->id);
});

test('the fake data of the congregation is seeded too', function () {
    expect(User::count())->toBe(1 + count(E2eSeeder::USERS) + E2eSeeder::OTHER_USERS_COUNT)
        ->and(Territory::count())->toBeGreaterThan(4);
});
