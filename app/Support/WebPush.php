<?php

namespace App\Support;

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
     * Public VAPID key, with which the browser subscribes to the push notifications of this server.
     */
    public static function publicKey(): ?string
    {
        return config('webpush.vapid.public_key');
    }
}
