<?php

namespace App\Listeners;

use App\Livewire\PushNotificationsPrompt;
use App\Providers\Filament\AppPanelProvider;
use Illuminate\Auth\Events\Login;

/**
 * Remember that the user just logged in, so that the first page of the app panel opens the offers to install the app
 * (`filament.pwa-install-prompt`) and to enable the push notifications on the device (`PushNotificationsPrompt`), and
 * the next ones do not. Each page takes the flag of the offer it renders.
 */
class OfferFullScreenPromptsAfterLogin
{
    public function handle(Login $event): void
    {
        session()->put([
            AppPanelProvider::PWA_INSTALL_OFFER_SESSION_KEY => true,
            PushNotificationsPrompt::OFFER_SESSION_KEY => true,
        ]);
    }
}
