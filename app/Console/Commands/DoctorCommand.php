<?php

namespace App\Console\Commands;

use App\Enums\InstallationCheckStatus;
use App\Support\InstallationChecks;
use Illuminate\Console\Command;

/**
 * Check the configuration of the installation and report what is missing or wrong, without changing anything (see
 * `InstallationChecks`, also shown by the first-run setup wizard).
 *
 * Meant for the installations without a shell (e.g. shared hosting), where it runs through the Artisan endpoint. The
 * errors (the application cannot work) make it fail; the warnings (a feature is off or degraded) do not.
 */
class DoctorCommand extends Command
{
    protected $signature = 'och:doctor';

    protected $description = 'Check the configuration of the installation and report what is missing or wrong';

    public function handle(InstallationChecks $checks): int
    {
        // Always in English, like the other Artisan commands
        $results = collect($checks->run('en'));

        foreach ($results as $result) {
            $status = match ($result['status']) {
                InstallationCheckStatus::Ok => '<fg=green;options=bold>OK</>',
                InstallationCheckStatus::Warning => '<fg=yellow;options=bold>WARNING</>',
                InstallationCheckStatus::Error => '<fg=red;options=bold>ERROR</>',
            };

            $this->components->twoColumnDetail(
                $result['check'].($result['details'] !== '' ? " <fg=gray>{$result['details']}</>" : ''),
                $status,
            );
        }

        $errors = $results->where('status', InstallationCheckStatus::Error)->count();
        $warnings = $results->where('status', InstallationCheckStatus::Warning)->count();

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
}
