<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;

/**
 * Create the keys signing the OAuth tokens of the AI assistants only when they are missing, so that the deployments can
 * run it every time (through `och:install`): replacing them would disconnect every assistant.
 *
 * The keys are created as `passport:keys` does, without calling it: the deployments run the Artisan commands from a
 * web request (`/api/artisan/execute`), where Passport does not register its commands.
 */
class EnsurePassportKeysCommand extends Command
{
    protected $signature = 'och:ensure-passport-keys
                            {--length=4096 : The length of the private key}';

    protected $description = 'Create the Passport encryption keys, unless they already exist';

    public function handle(): int
    {
        $publicKey = Passport::keyPath('oauth-public.key');
        $privateKey = Passport::keyPath('oauth-private.key');

        if (file_exists($privateKey) && file_exists($publicKey)) {
            $this->components->info('The Passport encryption keys already exist.');

            return self::SUCCESS;
        }

        $key = RSA::createKey((int) $this->option('length'));

        file_put_contents($publicKey, (string) $key->getPublicKey());
        file_put_contents($privateKey, (string) $key);

        if (! windows_os()) {
            chmod($publicKey, 0660);
            chmod($privateKey, 0600);
        }

        $this->components->info('The Passport encryption keys were created.');

        return self::SUCCESS;
    }
}
