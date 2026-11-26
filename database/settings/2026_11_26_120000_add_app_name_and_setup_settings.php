<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add the name of the application, starting from `APP_NAME`, and whether the first-run setup wizard was completed:
     * only new installations (without users yet) show it, the existing ones are already set up.
     */
    public function up(): void
    {
        $this->migrator->add('general.app_name', config('app.name'));
        $this->migrator->add('general.setup_completed', DB::table('users')->exists());
    }
};
