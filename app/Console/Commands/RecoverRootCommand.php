<?php

namespace App\Console\Commands;

use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;

/**
 * Give the access to the root user (ID 1) back, when nobody can manage the application anymore (e.g. its password was
 * lost, or its login or roles were removed in production): it sets its password, lets it log in again, marks its email
 * as verified, turns off its two-factor authentication and gives it the `super_admin` role.
 *
 * The password comes from `INITIAL_ROOT_PASSWORD`, or is asked for when run from a terminal. Since the Artisan endpoint
 * can run it too, it needs `--force` when it cannot ask for a confirmation, and it is always written to the log.
 */
class RecoverRootCommand extends Command
{
    protected $signature = 'och:recover-root
                            {--force : Run without asking for a confirmation (needed when not run from a terminal)}';

    protected $description = 'Give the access to the root user back: password, login, two-factor authentication and super admin role';

    public function handle(): int
    {
        $root = User::rootUser();

        if ($root === null) {
            $this->components->error('There is no root user (ID 1): run `php artisan och:install` to create it.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->components->error('Pass --force to recover the root user without a confirmation.');

                return self::FAILURE;
            }

            if (! confirm("Reset the password, the login and the two-factor authentication of {$root->email}, and make it a super admin?", default: false)) {
                $this->components->info('Nothing was changed.');

                return self::SUCCESS;
            }
        }

        $password = $this->newPassword();

        if ($password === null) {
            $this->components->error('Please set the INITIAL_ROOT_PASSWORD environment variable, or run the command from a terminal to type the password.');

            return self::FAILURE;
        }

        $twoFactorColumns = array_intersect_key(array_fill_keys([
            'app_authentication_secret',
            'app_authentication_recovery_codes',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
        ], null), $root->getAttributes());

        $root->forceFill([
            'password' => $password,
            'can_login' => true,
            // The verification email may be the very thing that cannot be received
            'email_verified_at' => $root->email_verified_at ?? now(),
            ...$twoFactorColumns,
        ]);

        // Quietly: outside of production the observer protects the password and the login of the root user
        $root->saveQuietly();

        $root->assignRole(Utils::createRole());

        Log::warning('The root user was recovered with `och:recover-root`.', ['email' => $root->email]);

        $this->components->info("The root user ({$root->email}) can log in again with the new password, as a super admin.");

        return self::SUCCESS;
    }

    /**
     * The password from the configuration, or typed in when run from a terminal.
     */
    private function newPassword(): ?string
    {
        $password = config('app.initial_root_user.password');

        if (filled($password)) {
            return $password;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        return password(
            label: 'New password of the root user',
            required: true,
            validate: fn (string $value): ?string => strlen($value) < 8 ? 'The password must be at least 8 characters.' : null,
        );
    }
}
