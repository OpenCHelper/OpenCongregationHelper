<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add the ID of the root user: the first user (ID 1) until the root user hands its role over to another super
     * administrator (see `App\Filament\Resources\Users\Actions\TransferRootAction`).
     */
    public function up(): void
    {
        $this->migrator->add('security.root_user_id', 1);
    }

    public function down(): void
    {
        $this->migrator->delete('security.root_user_id');
    }
};
