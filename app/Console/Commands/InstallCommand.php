<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Installation;
use App\Support\PasswordPolicy;
use BezhanSalleh\FilamentShield\Support\Utils;
use Database\Seeders\ShieldSeeder;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Set up what a new installation needs: the permissions of the roles (`ShieldSeeder`), the keys signing the OAuth tokens
 * of the AI assistants, and the root user, from the `INITIAL_ROOT_*` environment variables (or asked for, when run from
 * a terminal), with the `super_admin` role while no user has it yet.
 *
 * It only creates what is missing, so that the deployments can run it every time, and it needs no `--force` in
 * production. The migrations must have run first.
 */
class InstallCommand extends Command
{
    protected $signature = 'och:install';

    protected $description = 'Create the permissions, the keys of the AI assistants and the root user with the super admin role, unless they already exist';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => ShieldSeeder::class, '--force' => true]);

        if ($this->call('och:ensure-passport-keys') !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (User::query()->doesntExist() && ! $this->createRootUser()) {
            return self::FAILURE;
        }

        $this->grantSuperAdminRoleToRootUser();

        // The setup wizard is no longer offered
        Installation::markInstalled();

        return self::SUCCESS;
    }

    /**
     * Give the root user the `super_admin` role, only while nobody has it (a new installation): later, the roles of the
     * root user are the admins' call.
     */
    private function grantSuperAdminRoleToRootUser(): void
    {
        $root = User::rootUser();

        if ($root === null) {
            $this->components->warn('There is no root user (ID 1): no user was given the super admin role.');

            return;
        }

        $role = Utils::createRole();

        if ($role->users()->exists()) {
            $this->components->info('There is already a super admin: the roles of the root user were left as they are.');

            return;
        }

        $root->assignRole($role);
        $this->components->info("The root user ({$root->email}) is now a super admin.");
    }

    /**
     * Create the root user from the configuration, asking for the missing values when running interactively.
     *
     * The values are read through the configuration (not `env()`, which returns `null` once the configuration is
     * cached), and the user is created without the model factory, which relies on Faker.
     */
    private function createRootUser(): bool
    {
        /** @var array{name: ?string, email: ?string, password: ?string} $root */
        $root = config('app.initial_root_user');

        if (blank($root['name'] ?? null) || blank($root['email'] ?? null) || blank($root['password'] ?? null)) {
            if (! $this->input->isInteractive()) {
                $this->components->error('Please set the INITIAL_ROOT_USERNAME, INITIAL_ROOT_USERMAIL, and INITIAL_ROOT_PASSWORD environment variables.');

                return false;
            }

            $root = [
                'name' => filled($root['name'] ?? null) ? $root['name'] : text(label: 'Name of the root user', required: true),
                'email' => filled($root['email'] ?? null) ? $root['email'] : text(
                    label: 'Email address of the root user',
                    required: true,
                    validate: fn (string $email): ?string => filter_var($email, FILTER_VALIDATE_EMAIL) ? null : 'The email address must be valid.',
                ),
                'password' => filled($root['password'] ?? null) ? $root['password'] : $this->askPassword(),
            ];
        }

        // A password from `INITIAL_ROOT_PASSWORD` follows the password policy too: another one is asked for when
        // running interactively
        $passwordError = PasswordPolicy::errorFor($root['password']);

        if ($passwordError !== null) {
            $this->components->error("The INITIAL_ROOT_PASSWORD environment variable does not follow the password policy: {$passwordError}");

            if (! $this->input->isInteractive()) {
                return false;
            }

            $root['password'] = $this->askPassword();
        }

        [$firstName, $lastName] = array_pad(explode(' ', $root['name'], 2), 2, '');

        // The ID is set: the root user is the one with ID 1, while the next ID may be higher (e.g. after deleted users)
        User::forceCreate([
            'id' => 1,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $root['email'],
            'password' => $root['password'],
            'can_login' => true,
        ]);

        $this->components->info("The root user ({$root['email']}) was created.");

        return true;
    }

    private function askPassword(): string
    {
        return password(
            label: 'Password of the root user',
            required: true,
            validate: fn (string $password): ?string => PasswordPolicy::errorFor($password),
        );
    }
}
