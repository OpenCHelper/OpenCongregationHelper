<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
            $initial_root_username = env('INITIAL_ROOT_USERNAME', null);
            $initial_root_usermail = env('INITIAL_ROOT_USERMAIL', null);
            $initial_root_password = env('INITIAL_ROOT_PASSWORD', null);

            if (is_null($initial_root_username) || is_null($initial_root_password) || is_null($initial_root_usermail)) {
                throw new \Exception('Please set the INITIAL_ROOT_USERNAME, INITIAL_ROOT_USERMAIL, and INITIAL_ROOT_PASSWORD environment variables.');
            }

            User::factory()->create([
                'first_name' => explode(' ', $initial_root_username, 2)[0],
                'last_name' => explode(' ', $initial_root_username, 2)[1] ?? '',
                'can_login' => true,
                'email' => $initial_root_usermail,
                'password' => Hash::make($initial_root_password),
            ]);
        }
    }
}
