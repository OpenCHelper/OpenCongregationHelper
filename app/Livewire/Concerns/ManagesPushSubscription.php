<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\WebPush;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Minishlink\WebPush\ContentEncoding;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Store the push subscription of the current device for the user, for the components that enable the push notifications
 * (see `resources/js/push-subscription.js`).
 *
 * Only the browser knows whether the device is subscribed: it subscribes with the public VAPID key and sends the
 * subscription here. Each device of the user has its own subscription.
 */
trait ManagesPushSubscription
{
    /**
     * Whether the subscription of the current device belongs to the user.
     *
     * A device subscribed by another user (e.g. a shared device) is not taken over until the user enables the push
     * notifications on it.
     */
    public function ownsSubscription(string $endpoint): bool
    {
        return $this->user()->pushSubscriptions()->where('endpoint', $endpoint)->exists();
    }

    /**
     * Store the subscription of the current device (or update it, as the browser may renew it).
     *
     * A device subscribed by another user moves to the current one only with the same keys, which only the browser of
     * that device knows: knowing the endpoint alone is not enough to take the device over.
     *
     * @param  array{endpoint?: mixed, keys?: array{p256dh?: mixed, auth?: mixed}, contentEncoding?: mixed}  $subscription
     */
    public function subscribe(array $subscription): void
    {
        abort_unless(WebPush::canSubscribe(), 404);

        $validated = Validator::make($subscription, [
            'endpoint' => ['required', 'string', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', Rule::enum(ContentEncoding::class)],
        ])->validate();

        $existingSubscription = PushSubscription::findByEndpoint($validated['endpoint']);

        if ($existingSubscription
            && ! $this->user()->ownsPushSubscription($existingSubscription)
            && ($existingSubscription->public_key !== $validated['keys']['p256dh'] || $existingSubscription->auth_token !== $validated['keys']['auth'])) {
            throw ValidationException::withMessages(['endpoint' => __('profile.push_notifications.error')]);
        }

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

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
