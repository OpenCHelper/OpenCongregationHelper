<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Check a password against the password policy (`Password::default()`, configured in `AppServiceProvider`) outside of
 * a form, e.g. the passwords of the root user typed in or set in `INITIAL_ROOT_PASSWORD` for the Artisan commands.
 */
class PasswordPolicy
{
    /**
     * The first rule of the policy that the password breaks, translated, or `null` when it follows the policy.
     */
    public static function errorFor(string $password): ?string
    {
        $error = Validator::make(['password' => $password], ['password' => ['required', Password::default()]])
            ->errors()
            ->first('password');

        return $error === '' ? null : $error;
    }
}
