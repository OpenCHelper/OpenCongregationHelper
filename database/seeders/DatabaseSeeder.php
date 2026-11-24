<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the data a new installation needs (see `och:install`).
     */
    public function run(): void
    {
        $exitCode = Artisan::call('och:install', ['--no-interaction' => true], $this->command?->getOutput());

        if ($exitCode !== 0) {
            throw new RuntimeException('Please set the INITIAL_ROOT_USERNAME, INITIAL_ROOT_USERMAIL, and INITIAL_ROOT_PASSWORD environment variables.');
        }
    }
}
