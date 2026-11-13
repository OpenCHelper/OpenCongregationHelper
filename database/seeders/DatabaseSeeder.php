<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Log::info('Seeding the database with initial data...');

        // Initialize root access user
        if (User::count() === 0) {
            $this->createRootUser();
        }
    }

    /**
     * Create the root user from the `INITIAL_ROOT_*` environment variables.
     *
     * The values are read through the configuration (not `env()`, which returns
     * `null` once the configuration is cached), and the user is created without
     * the model factory, which relies on Faker.
     */
    private function createRootUser(): void
    {
        /** @var array{name: ?string, email: ?string, password: ?string} $root */
        $root = config('app.initial_root_user');

        if (blank($root['name'] ?? null) || blank($root['email'] ?? null) || blank($root['password'] ?? null)) {
            throw new RuntimeException('Please set the INITIAL_ROOT_USERNAME, INITIAL_ROOT_USERMAIL, and INITIAL_ROOT_PASSWORD environment variables.');
        }

        [$firstName, $lastName] = array_pad(explode(' ', $root['name'], 2), 2, '');

        User::forceCreate([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $root['email'],
            'password' => $root['password'],
            'can_login' => true,
        ]);
    }
}
