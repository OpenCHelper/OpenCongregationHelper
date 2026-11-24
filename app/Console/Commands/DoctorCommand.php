<?php

namespace App\Console\Commands;

use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Throwable;

/**
 * Check the configuration of the installation and report what is missing or wrong, without changing anything.
 *
 * Meant for the installations without a shell (e.g. shared hosting), where it runs through the Artisan endpoint. The
 * errors (the application cannot work) make it fail; the warnings (a feature is off or degraded) do not.
 */
class DoctorCommand extends Command
{
    protected $signature = 'och:doctor';

    protected $description = 'Check the configuration of the installation and report what is missing or wrong';

    private const OK = 'ok';

    private const WARNING = 'warning';

    private const ERROR = 'error';

    /**
     * @var list<array{check: string, status: string, details: string}>
     */
    private array $results = [];

    public function handle(Migrator $migrator): int
    {
        $this->checkConfiguration();
        $databaseIsReachable = $this->checkDatabase($migrator);
        $this->checkFiles();

        if ($databaseIsReachable) {
            $this->checkUsers();
        }

        foreach ($this->results as $result) {
            $status = match ($result['status']) {
                self::OK => '<fg=green;options=bold>OK</>',
                self::WARNING => '<fg=yellow;options=bold>WARNING</>',
                default => '<fg=red;options=bold>ERROR</>',
            };

            $this->components->twoColumnDetail(
                $result['check'].($result['details'] !== '' ? " <fg=gray>{$result['details']}</>" : ''),
                $status,
            );
        }

        $errors = collect($this->results)->where('status', self::ERROR)->count();
        $warnings = collect($this->results)->where('status', self::WARNING)->count();

        $this->newLine();

        if ($errors > 0) {
            $this->components->error("{$errors} error(s) and {$warnings} warning(s) found.");

            return self::FAILURE;
        }

        $warnings > 0
            ? $this->components->warn("No errors, {$warnings} warning(s) found.")
            : $this->components->info('Everything looks fine.');

        return self::SUCCESS;
    }

    private function checkConfiguration(): void
    {
        $this->report('Application key (APP_KEY)', filled(config('app.key')) ? self::OK : self::ERROR,
            filled(config('app.key')) ? '' : 'run `php artisan key:generate`');

        $url = (string) config('app.url');
        $urlIsDefault = blank($url) || str_contains($url, 'localhost');
        $this->report('Application URL (APP_URL)', $urlIsDefault && $this->laravel->environment('production') ? self::ERROR : ($urlIsDefault ? self::WARNING : self::OK),
            $urlIsDefault ? 'set it to the public address: the links in the emails use it' : $url);

        $this->report('Debug mode (APP_DEBUG)', config('app.debug') && $this->laravel->environment('production') ? self::WARNING : self::OK,
            config('app.debug') && $this->laravel->environment('production') ? 'turn it off in production: errors would show internal details' : '');

        $this->report('Queue and scheduler endpoints (ARTISAN_CRON_TOKEN)', filled(config('app.artisan_cron_token')) ? self::OK : self::WARNING,
            filled(config('app.artisan_cron_token')) ? '' : 'disabled: without a shell, the queued emails and the scheduled tasks never run');

        $mailIsSet = filled(config('mail.default')) && filled(config('mail.from.address'));
        $this->report('Email (MAIL_MAILER, MAIL_FROM_ADDRESS)', $mailIsSet ? self::OK : self::WARNING,
            $mailIsSet ? (string) config('mail.default') : 'the notification emails cannot be sent');

        $rootIsSet = filled(config('app.initial_root_user.email')) && filled(config('app.initial_root_user.password'));
        $this->report('Initial root user (INITIAL_ROOT_*)', $rootIsSet ? self::OK : self::WARNING,
            $rootIsSet ? '' : 'needed by `och:install` on a new installation, and by `och:recover-root`');
    }

    private function checkDatabase(Migrator $migrator): bool
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->report('Database connection', self::ERROR, $exception->getMessage());

            return false;
        }

        $this->report('Database connection', self::OK, (string) DB::connection()->getDatabaseName());

        if (! $migrator->repositoryExists()) {
            $this->report('Migrations', self::ERROR, 'the database is empty: run `php artisan och:deploy`');

            return false;
        }

        $ran = $migrator->getRepository()->getRan();
        $pending = collect($migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]))
            ->keys()
            ->diff($ran)
            ->count();

        $this->report('Migrations', $pending === 0 ? self::OK : self::ERROR,
            $pending === 0 ? '' : "{$pending} pending: run `php artisan och:deploy`");

        return $pending === 0;
    }

    private function checkFiles(): void
    {
        $link = public_path('storage');
        $this->report('Public storage link (public/storage)', is_link($link) ? self::OK : self::WARNING,
            is_link($link) ? '' : 'missing: the uploaded files cannot be shown, run `php artisan storage:link`');

        $keysExist = file_exists(Passport::keyPath('oauth-private.key')) && file_exists(Passport::keyPath('oauth-public.key'));
        $this->report('Keys of the AI assistants', $keysExist ? self::OK : self::WARNING,
            $keysExist ? '' : 'missing: the AI assistants cannot connect, run `php artisan och:install`');

        $this->report('Writable storage', is_writable(storage_path('framework')) && is_writable(storage_path('logs')) ? self::OK : self::ERROR,
            is_writable(storage_path('framework')) && is_writable(storage_path('logs')) ? '' : 'storage/framework and storage/logs must be writable');
    }

    private function checkUsers(): void
    {
        $root = User::rootUser();
        $this->report('Root user (ID 1)', $root !== null ? self::OK : self::ERROR,
            $root?->email ?? 'missing: run `php artisan och:install`');

        if ($root !== null && ! $root->can_login) {
            $this->report('Root user can log in', self::WARNING, 'login disabled: run `php artisan och:recover-root` if nobody else can manage the users');
        }

        $superAdmins = Utils::getRoleModel()::query()
            ->where('name', Utils::getSuperAdminName())
            ->first()?->users()->count() ?? 0;

        $this->report('Super administrators', $superAdmins > 0 ? self::OK : self::ERROR,
            $superAdmins > 0 ? (string) $superAdmins : 'nobody can manage the application: run `php artisan och:install`');
    }

    private function report(string $check, string $status, string $details = ''): void
    {
        $this->results[] = ['check' => $check, 'status' => $status, 'details' => $details];
    }
}
