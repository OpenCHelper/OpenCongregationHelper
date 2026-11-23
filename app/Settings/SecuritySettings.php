<?php

namespace App\Settings;

class SecuritySettings extends AppSettings
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

    public static function group(): string
    {
        return 'security';
    }
}
