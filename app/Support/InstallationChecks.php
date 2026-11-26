<?php

namespace App\Support;

use App\Enums\InstallationCheckStatus;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Throwable;

/**
 * Check the configuration of the installation and report what is missing or wrong, without changing anything.
 *
 * Shared by `och:doctor` (in English, for the terminal and the Artisan endpoint) and the first-run setup wizard (in the
 * language of the user). The texts come from `lang/<locale>/installation.php`.
 */
class InstallationChecks
{
    /**
     * @var list<array{key: string, check: string, status: InstallationCheckStatus, details: string}>
     */
    private array $results = [];

    private ?string $locale = null;

    public function __construct(private Migrator $migrator) {}

    /**
     * Run the checks, in the given language (the one of the application when `null`).
     *
     * @return list<array{key: string, check: string, status: InstallationCheckStatus, details: string}>
     */
    public function run(?string $locale = null): array
    {
        $this->results = [];
        $this->locale = $locale;

        $this->checkConfiguration();
        $databaseIsReachable = $this->checkDatabase();
        $this->checkFiles();

        if ($databaseIsReachable) {
            $this->checkUsers();
        }

        return $this->results;
    }

    private function checkConfiguration(): void
    {
        $isProduction = app()->environment('production');

        $this->check('app_key', filled(config('app.key')), InstallationCheckStatus::Error);

        $url = (string) config('app.url');
        $this->check('app_url', filled($url) && ! str_contains($url, 'localhost'),
            $isProduction ? InstallationCheckStatus::Error : InstallationCheckStatus::Warning, $url);

        $this->check('https', str_starts_with($url, 'https://'), InstallationCheckStatus::Warning);

        $this->check('debug', ! ($isProduction && config('app.debug')), InstallationCheckStatus::Warning);

        $this->report('timezone', InstallationCheckStatus::Ok, (string) config('app.timezone'));

        // Optional: only the installations without a shell run the queue and the scheduler through these endpoints
        $this->report('cron_token', InstallationCheckStatus::Ok, $this->text(
            filled(config('app.artisan_cron_token')) ? 'checks.cron_token.enabled' : 'checks.cron_token.disabled',
        ));

        $this->check('mail', filled(config('mail.default')) && filled(config('mail.from.address')),
            InstallationCheckStatus::Warning, (string) config('mail.default'));

        $this->check('push', WebPush::isConfigured(), InstallationCheckStatus::Warning);

        $this->check('initial_root', filled(config('app.initial_root_user.email')) && filled(config('app.initial_root_user.password')),
            InstallationCheckStatus::Warning);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->report('database', InstallationCheckStatus::Error, $exception->getMessage());

            return false;
        }

        $this->report('database', InstallationCheckStatus::Ok, (string) DB::connection()->getDatabaseName());

        if (! $this->migrator->repositoryExists()) {
            $this->report('migrations', InstallationCheckStatus::Error, $this->text('checks.migrations.empty'));

            return false;
        }

        $ran = $this->migrator->getRepository()->getRan();
        $pending = collect($this->migrator->getMigrationFiles([database_path('migrations'), ...$this->migrator->paths()]))
            ->keys()
            ->diff($ran)
            ->count();

        $this->check('migrations', $pending === 0, InstallationCheckStatus::Error, failedReplace: ['count' => $pending]);

        return $pending === 0;
    }

    private function checkFiles(): void
    {
        $this->check('storage_link', is_link(public_path('storage')), InstallationCheckStatus::Warning);

        $this->check('passport_keys', file_exists(Passport::keyPath('oauth-private.key')) && file_exists(Passport::keyPath('oauth-public.key')),
            InstallationCheckStatus::Warning);

        $this->check('writable_storage', is_writable(storage_path('framework')) && is_writable(storage_path('logs')),
            InstallationCheckStatus::Error);
    }

    private function checkUsers(): void
    {
        $root = User::rootUser();
        $this->check('root_user', $root !== null, InstallationCheckStatus::Error, (string) $root?->email);

        if ($root !== null && ! $root->can_login) {
            $this->report('root_can_login', InstallationCheckStatus::Warning, $this->text('checks.root_can_login.failed'));
        }

        if ($root !== null) {
            $this->check('root_two_factor', $root->hasTwoFactorAuthentication(), InstallationCheckStatus::Warning);
        }

        $superAdmins = Utils::getRoleModel()::query()
            ->where('name', Utils::getSuperAdminName())
            ->first()?->users()->count() ?? 0;

        $this->check('super_admins', $superAdmins > 0, InstallationCheckStatus::Error, (string) $superAdmins);
    }

    /**
     * Report a check: OK (with the `$passedDetails`) when it `$passes`, otherwise the `$failedStatus` (an error or a
     * warning) with what to do about it (`installation.checks.<key>.failed`).
     *
     * @param  array<string, mixed>  $failedReplace
     */
    private function check(string $key, bool $passes, InstallationCheckStatus $failedStatus, string $passedDetails = '', array $failedReplace = []): void
    {
        $passes
            ? $this->report($key, InstallationCheckStatus::Ok, $passedDetails)
            : $this->report($key, $failedStatus, $this->text("checks.{$key}.failed", $failedReplace));
    }

    private function report(string $key, InstallationCheckStatus $status, string $details = ''): void
    {
        $this->results[] = [
            'key' => $key,
            'check' => $this->text("checks.{$key}.label"),
            'status' => $status,
            'details' => $details,
        ];
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        return (string) __("installation.{$key}", $replace, $this->locale);
    }
}
