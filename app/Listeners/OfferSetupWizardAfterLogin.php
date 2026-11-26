<?php

namespace App\Listeners;

use App\Filament\Pages\SetupWizard;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Auth\Events\Login;

/**
 * Remember that the root user just logged in while the first-run setup is not completed, so that the first page opens
 * the setup wizard (see `RedirectToSetupWizard`).
 */
class OfferSetupWizardAfterLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User && $event->user->isRootUser() && ! app(GeneralSettings::class)->setup_completed) {
            session()->put(SetupWizard::OFFER_SESSION_KEY, true);
        }
    }
}
