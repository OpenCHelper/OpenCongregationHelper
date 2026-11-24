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
        // Artisan keeps the same instance: the results of a previous run in the same process must not be shown again
        $this->results = [];

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
        $isProduction = $this->laravel->environment('production');

        $this->check('Application key (APP_KEY)', filled(config('app.key')), self::ERROR,
            'run `php artisan key:generate`');

        $url = (string) config('app.url');
        $this->check('Application URL (APP_URL)', filled($url) && ! str_contains($url, 'localhost'),
            $isProduction ? self::ERROR : self::WARNING, 'set it to the public address: the links in the emails use it', $url);

        $this->check('Debug mode (APP_DEBUG)', ! ($isProduction && config('app.debug')), self::WARNING,
            'turn it off in production: errors would show internal details');

        // Optional: only the installations without a shell run the queue and the scheduler through these endpoints
        $this->report('Queue and scheduler endpoints (ARTISAN_CRON_TOKEN)', self::OK,
            filled(config('app.artisan_cron_token')) ? 'enabled' : 'disabled (only needed without a shell)');

        $this->check('Email (MAIL_MAILER, MAIL_FROM_ADDRESS)', filled(config('mail.default')) && filled(config('mail.from.address')),
            self::WARNING, 'the notification emails cannot be sent', (string) config('mail.default'));

        $this->check('Initial root user (INITIAL_ROOT_*)', filled(config('app.initial_root_user.email')) && filled(config('app.initial_root_user.password')),
            self::WARNING, 'needed by `och:install` on a new installation, and by `och:recover-root`');
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
            $this->report('Migrations', self::ERROR, 'the database is empty: run `php artisan och:post-deploy`');

            return false;
        }

        $ran = $migrator->getRepository()->getRan();
        $pending = collect($migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]))
            ->keys()
            ->diff($ran)
            ->count();

        $this->check('Migrations', $pending === 0, self::ERROR, "{$pending} pending: run `php artisan och:post-deploy`");

        return $pending === 0;
    }

    private function checkFiles(): void
    {
        $this->check('Public storage link (public/storage)', is_link(public_path('storage')), self::WARNING,
            'missing: the uploaded files cannot be shown, run `php artisan storage:link`');

        $this->check('Keys of the AI assistants', file_exists(Passport::keyPath('oauth-private.key')) && file_exists(Passport::keyPath('oauth-public.key')),
            self::WARNING, 'missing: the AI assistants cannot connect, run `php artisan och:install`');

        $this->check('Writable storage', is_writable(storage_path('framework')) && is_writable(storage_path('logs')),
            self::ERROR, 'storage/framework and storage/logs must be writable');
    }

    private function checkUsers(): void
    {
        $root = User::rootUser();
        $this->check('Root user (ID 1)', $root !== null, self::ERROR, 'missing: run `php artisan och:install`', (string) $root?->email);

        if ($root !== null && ! $root->can_login) {
            $this->report('Root user can log in', self::WARNING, 'login disabled: run `php artisan och:recover-root` if nobody else can manage the users');
        }

        $superAdmins = Utils::getRoleModel()::query()
            ->where('name', Utils::getSuperAdminName())
            ->first()?->users()->count() ?? 0;

        $this->check('Super administrators', $superAdmins > 0, self::ERROR,
            'nobody can manage the application: run `php artisan och:install`', (string) $superAdmins);
    }

    /**
     * Report a check: OK (with the `$passedDetails`) when it `$passes`, otherwise the `$failedStatus` (an error or a
     * warning) with what to do about it.
     */
    private function check(string $check, bool $passes, string $failedStatus, string $failedDetails, string $passedDetails = ''): void
    {
        $passes
            ? $this->report($check, self::OK, $passedDetails)
            : $this->report($check, $failedStatus, $failedDetails);
    }

    private function report(string $check, string $status, string $details = ''): void
    {
        $this->results[] = ['check' => $check, 'status' => $status, 'details' => $details];
    }
}
