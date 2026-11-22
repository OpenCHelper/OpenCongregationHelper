<?php

namespace App\Listeners;

use App\Models\SharedUrl;
use App\Settings\TerritorySettings;
use Spatie\LaravelSettings\Events\SettingsSaved;

class DeleteSharedUrlsWhenSharingIsDisabled
{
    /**
     * Delete every shared link once sharing is disabled from the territory settings, so that none works again when
     * sharing is enabled back.
     */
    public function handle(SettingsSaved $event): void
    {
        if ($event->settings instanceof TerritorySettings && ! $event->settings->sharing_enabled) {
            SharedUrl::query()->delete();
        }
    }
}
