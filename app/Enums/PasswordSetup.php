<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How the password of a user who is given access is set, when they have none yet.
 */
enum PasswordSetup: string implements HasDescription, HasLabel
{
    /** The user gets an email to choose their own password. */
    case Invitation = 'invitation';

    /** The administrator types the password. */
    case Password = 'password';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invitation => __('filament-resources.user.password_setup_invitation'),
            self::Password => __('filament-resources.user.password_setup_password'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Invitation => __('filament-resources.user.password_setup_invitation_description'),
            self::Password => __('filament-resources.user.password_setup_password_description'),
        };
    }
}
