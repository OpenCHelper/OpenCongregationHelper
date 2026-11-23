<?php

namespace App\Listeners;

use App\Livewire\PushNotificationsPrompt;
use Illuminate\Auth\Events\Login;

/**
 * Remember that the user just logged in, so that the first page of the app panel offers to enable the push
 * notifications on the device (see `PushNotificationsPrompt`), and the next ones do not.
 */
class OfferPushNotificationsAfterLogin
{
    public function handle(Login $event): void
    {
        session()->put(PushNotificationsPrompt::OFFER_SESSION_KEY, true);
    }
}
