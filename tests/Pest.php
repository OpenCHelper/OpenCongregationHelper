<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Whether Vite can serve the frontend assets: built with `npm run build`
 * (as in CI) or served by the development server.
 */
function frontendAssetsAreAvailable(): bool
{
    return Vite::isRunningHot() || Vite::manifestHash() !== null;
}

/**
 * Sign the OAuth tokens with keys of a test size, outside of the storage of the app, created once per test run.
 */
function usePassportTestKeys(): void
{
    $keys = sys_get_temp_dir().'/passport-test-keys-'.getmypid();

    if (! file_exists($keys.'/oauth-private.key')) {
        if (! is_dir($keys)) {
            mkdir($keys);
        }

        $key = RSA::createKey(2048);
        file_put_contents($keys.'/oauth-public.key', (string) $key->getPublicKey());
        file_put_contents($keys.'/oauth-private.key', (string) $key);
        chmod($keys.'/oauth-public.key', 0600);
        chmod($keys.'/oauth-private.key', 0600);
    }

    Passport::loadKeysFrom($keys);
}
