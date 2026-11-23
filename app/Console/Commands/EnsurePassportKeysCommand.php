<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;

/**
 * Create the keys signing the OAuth tokens of the AI assistants (`passport:keys`) only when they are missing, so that
 * the deployments can run it every time: replacing them would disconnect every assistant.
 */
class EnsurePassportKeysCommand extends Command
{
    protected $signature = 'app:ensure-passport-keys';

    protected $description = 'Create the Passport encryption keys, unless they already exist';

    public function handle(): int
    {
        if (file_exists(Passport::keyPath('oauth-private.key')) && file_exists(Passport::keyPath('oauth-public.key'))) {
            $this->components->info('The Passport encryption keys already exist.');

            return self::SUCCESS;
        }

        return $this->call('passport:keys', ['--force' => true]);
    }
}
