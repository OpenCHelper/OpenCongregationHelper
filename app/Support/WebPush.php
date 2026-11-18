<?php

namespace App\Support;

use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * Push notifications of the browser (Web Push), shown by the service worker of the Progressive Web App.
 *
 * They need a pair of VAPID keys (`php artisan webpush:vapid`): without them, nobody can subscribe and the
 * notifications are only sent by email and inside the app.
 */
class WebPush
{
    public static function isConfigured(): bool
    {
        return filled(self::publicKey()) && filled(config('webpush.vapid.private_key'));
    }

    /**
     * Whether the current user can enable the push notifications on this device.
     *
     * Not while impersonating: the device of the administrator would receive the notifications of the impersonated user.
     */
    public static function canSubscribe(): bool
    {
        return self::isConfigured() && ! Impersonation::isImpersonating();
    }

    /**
     * Public VAPID key, with which the browser subscribes to the push notifications of this server.
     */
    public static function publicKey(): ?string
    {
        return config('webpush.vapid.public_key');
    }
}
