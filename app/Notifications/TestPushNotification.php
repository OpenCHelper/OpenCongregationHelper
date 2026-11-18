<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Push notification sent from the profile, to check that the devices of the user receive the push notifications.
 */
class TestPushNotification extends Notification
{
    use SendsWebPush;

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->isWebPushAvailable($notifiable)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * Content of the push notification (see `SendsWebPush::toWebPush()`).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('profile.push_notifications.test_title'))
            ->body(__('profile.push_notifications.test_body'))
            ->icon(Heroicon::OutlinedBell)
            ->getDatabaseMessage();
    }
}
