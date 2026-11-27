<?php

namespace App\Settings;

class SecuritySettings extends ConfigSettings
{
    /**
     * Whether the users must set up two-factor authentication before using the app (otherwise they can still enable it
     * from their profile).
     */
    public bool $require_two_factor = false;

    /**
     * Whether the users must confirm their email address before using the app.
     */
    public bool $require_email_verification = false;

    /**
     * Whether the users can connect AI assistants (e.g. ChatGPT, Claude) to the app through its MCP server, letting
     * them read and change what the user can (see `routes/ai.php`).
     */
    public bool $ai_assistants_enabled = false;

    /**
     * Whether the passwords chosen by the users are checked against the known data leaks of Have I Been Pwned.
     */
    public ?bool $password_leak_check = null;

    /**
     * The ID of the root user: the first user, until the root user hands its role over to another super administrator.
     * Read it through `User::rootUserId()`, which also works before this setting exists (e.g. during the installation).
     */
    public int $root_user_id = 1;

    public static function group(): string
    {
        return 'security';
    }

    public static function configKeys(): array
    {
        return [
            'password_leak_check' => 'auth.password_leak_check.enabled',
        ];
    }
}
