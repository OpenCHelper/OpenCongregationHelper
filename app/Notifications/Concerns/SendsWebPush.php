<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Support\WebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Also sends the notification as a push notification, to the devices on which the user enabled them: `via()` adds
 * `WebPushChannel::class` to its channels when `isWebPushAvailable()`.
 *
 * The push notification shows the same title and body as the database notification (`toDatabase()`), and opens the
 * page of its first action when clicked.
 */
trait SendsWebPush
{
    /**
     * Whether the notification can be sent as a push notification: the push notifications are configured and the user
     * enabled them on at least one device.
     *
     * Users who cannot log in (anymore) get no push notifications: their devices may now belong to someone else.
     */
    protected function isWebPushAvailable(object $notifiable): bool
    {
        return WebPush::isConfigured()
            && ! ($notifiable instanceof User && $notifiable->isVirtual())
            && method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists();
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        /** @var array{title?: ?string, body?: ?string, actions?: list<array{url?: ?string}>} $message */
        $message = $this->toDatabase($notifiable);

        $url = collect($message['actions'] ?? [])->pluck('url')->filter()->first() ?? route('dashboard');

        return (new WebPushMessage)
            ->title((string) ($message['title'] ?? config('app.name')))
            ->body((string) ($message['body'] ?? ''))
            ->icon(asset('icons/icon-192.png'))
            ->badge(asset('icons/favicon-32.png'))
            ->lang(app()->getLocale())
            ->data(['url' => $url])
            ->options(['TTL' => 60 * 60 * 24 * 7]);
    }
}
