<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesPushSubscription;
use App\Support\WebPush;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-screen dialog that offers to enable the push notifications on the current device, once logged in.
 *
 * Only the browser knows whether the device can receive them and is subscribed: the dialog opens only when it can and
 * is not, and the user has not dismissed it recently. The section of the profile enables them too.
 */
class PushNotificationsPrompt extends Component
{
    use ManagesPushSubscription;

    /**
     * Days the dialog stays hidden after the user dismissed it.
     */
    public const DISMISS_DAYS = 30;

    public function render(): View
    {
        return view('livewire.push-notifications-prompt', [
            'publicKey' => WebPush::publicKey(),
        ]);
    }
}
