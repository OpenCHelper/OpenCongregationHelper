<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the Admin user
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory(10)->withPersonalTeam()->create();
    }
}
