<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesPushSubscription;
use App\Notifications\TestPushNotification;
use App\Support\WebPush;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Section of the profile to enable the push notifications on the current device.
 *
 * Only the browser knows whether the device is subscribed: it subscribes with the public VAPID key and sends the
 * subscription here, to be stored for the user. Each device of the user has its own subscription.
 */
class ProfilePushNotifications extends Component
{
    use ManagesPushSubscription;

    /**
     * Test notifications that a user can send per minute, as each one calls the push services of all their devices.
     */
    public const TEST_NOTIFICATIONS_PER_MINUTE = 3;

    /**
     * Send a push notification to all the devices of the user, to check that they receive it.
     */
    public function sendTest(): void
    {
        abort_unless(WebPush::canSubscribe(), 404);

        $user = $this->user();

        if (! $user->pushSubscriptions()->exists()) {
            Notification::make()
                ->title(__('profile.push_notifications.test_no_devices'))
                ->warning()
                ->send();

            return;
        }

        $rateLimiterKey = 'push-test:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::TEST_NOTIFICATIONS_PER_MINUTE)) {
            Notification::make()
                ->title(__('profile.push_notifications.test_too_many'))
                ->warning()
                ->send();

            return;
        }

        RateLimiter::hit($rateLimiterKey);

        $user->notifyNow(new TestPushNotification);

        Notification::make()
            ->title(__('profile.push_notifications.test_sent'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.profile-push-notifications', [
            'canSubscribe' => WebPush::canSubscribe(),
            'publicKey' => WebPush::publicKey(),
        ]);
    }
}
