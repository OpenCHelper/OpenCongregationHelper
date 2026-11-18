<?php

namespace App\Settings;

class NotificationSettings extends ConfigSettings
{
    /**
     * The name shown as the sender of the emails.
     */
    public ?string $mail_from_name;

    public static function group(): string
    {
        return 'notifications';
    }

    public static function configKeys(): array
    {
        return [
            'mail_from_name' => 'mail.from.name',
        ];
    }
}
