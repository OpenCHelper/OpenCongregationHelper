<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Run everything a deployment needs once the files of a new version are in place: the migrations, `och:install`, the
 * `public/storage` link and the caches.
 *
 * It replaces the list of commands each deployment used to run, and it works through the Artisan endpoint too
 * (`/api/artisan/execute`), so that the installations without a shell can be updated in one call. It stops at the
 * first failing step, with a failure exit code.
 */
class DeployCommand extends Command
{
    protected $signature = 'och:deploy
                            {--fresh : Drop all the tables before migrating, deleting ALL the data (refused in production)}';

    protected $description = 'Migrate the database, set up what is missing (och:install) and rebuild the caches after deploying a new version';

    public function handle(): int
    {
        if ($this->option('fresh') && $this->laravel->environment('production')) {
            $this->components->error('The --fresh option would delete all the data: it is refused in production.');

            return self::FAILURE;
        }

        // Through the Artisan endpoint all the steps run in a single request, which must not time out halfway (the
        // function is not defined where the hosting disables it)
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        foreach ($this->steps() as [$command, $arguments]) {
            $this->components->info("Running `{$command}`");

            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $this->components->error("`{$command}` failed: the deployment was stopped.");

                return self::FAILURE;
            }
        }

        $this->components->info('Deployment completed.');

        return self::SUCCESS;
    }

    /**
     * The commands to run, in order, with their arguments.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public function steps(): array
    {
        return [
            // `--force`: the commands are refused in production without it, as they cannot ask for a confirmation
            [$this->option('fresh') ? 'migrate:fresh' : 'migrate', ['--force' => true]],
            ['och:install', $this->input->isInteractive() ? [] : ['--no-interaction' => true]],

            // Replaces a link left by a previous deployment (a folder must be removed first)
            ['storage:link', ['--force' => true]],

            // The caches of the previous version, then the new ones, step by step (`optimize` would show nothing)
            ['optimize:clear', []],
            ['filament:clear-cached-components', []],
            ['filament:optimize-clear', []],
            ['config:cache', []],
            ['event:cache', []],
            ['route:cache', []],
            ['view:cache', []],
            ['filament:cache-components', []],
            ['filament:optimize', []],

            // The sub-minute tasks still running the previous version - https://laravel.com/docs/12.x/scheduling#interrupting-sub-minute-tasks
            ['schedule:interrupt', []],
        ];
    }
}
