<?php

namespace App\Notifications\Concerns;

use App\Support\WebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Also sends the notification as a push notification, to the devices on which the user enabled them.
 *
 * The push notification shows the same title and body as the database notification (`toDatabase()`), and opens the
 * page of its first action when clicked.
 */
trait SendsWebPush
{
    /**
     * Add the push channel to the given ones, when the push notifications are configured and the user enabled them.
     *
     * @param  list<string>  $channels
     * @return list<string>
     */
    protected function withWebPush(object $notifiable, array $channels): array
    {
        if (WebPush::isConfigured()
            && method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        /** @var array{title?: ?string, body?: ?string, actions?: list<array{url?: ?string}>} $message */
        $message = $this->toDatabase($notifiable);

        $url = collect($message['actions'] ?? [])->pluck('url')->filter()->first() ?? route('dashboard');

        return (new WebPushMessage)
            ->title(strip_tags((string) ($message['title'] ?? config('app.name'))))
            ->body(strip_tags((string) ($message['body'] ?? '')))
            ->icon(asset('icons/icon-192.png'))
            ->badge(asset('icons/favicon-32.png'))
            ->lang(app()->getLocale())
            ->data(['url' => $url])
            ->options(['TTL' => 60 * 60 * 24 * 7]);
    }
}
