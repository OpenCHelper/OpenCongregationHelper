<?php

namespace App\Livewire;

use App\Models\User;
use App\Notifications\TestPushNotification;
use App\Support\WebPush;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Minishlink\WebPush\ContentEncoding;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Section of the profile to enable the push notifications on the current device.
 *
 * Only the browser knows whether the device is subscribed: it subscribes with the public VAPID key and sends the
 * subscription here, to be stored for the user. Each device of the user has its own subscription.
 */
class ProfilePushNotifications extends Component
{
    /**
     * Store the subscription of the current device (or update it, as the browser may renew it).
     *
     * @param  array{endpoint?: mixed, keys?: array{p256dh?: mixed, auth?: mixed}, contentEncoding?: mixed}  $subscription
     */
    public function subscribe(array $subscription): void
    {
        abort_unless(WebPush::isConfigured(), 404);

        $validated = Validator::make($subscription, [
            'endpoint' => ['required', 'string', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', Rule::enum(ContentEncoding::class)],
        ])->validate();

        $this->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? ContentEncoding::aes128gcm->value,
        );
    }

    /**
     * Forget the subscription of the current device.
     */
    public function unsubscribe(string $endpoint): void
    {
        $this->user()->deletePushSubscription($endpoint);
    }

    /**
     * Send a push notification to all the devices of the user, to check that they receive it.
     */
    public function sendTest(): void
    {
        abort_unless(WebPush::isConfigured(), 404);

        $this->user()->notifyNow(new TestPushNotification);

        Notification::make()
            ->title(__('profile.push_notifications.test_sent'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.profile-push-notifications', [
            'isConfigured' => WebPush::isConfigured(),
            'publicKey' => WebPush::publicKey(),
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
