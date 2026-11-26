<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
    By default every peer is trusted as a proxy (`TRUSTED_PROXIES=*`, see
    `bootstrap/app.php` and GH36), so the forwarded headers below can be sent
    by any client. Only the scheme and the
    client IP may be taken from them; the host, port and prefix of the URLs the
    application generates must always come from the request itself.
*/

beforeEach(function () {
    // Render the real assets when they are built (as in CI), so that entries missing
    // from the Vite build are caught, and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    $this->spoofedHeaders = [
        'X-Forwarded-Host' => 'evil.example',
        'X-Forwarded-Port' => '8443',
        'X-Forwarded-Prefix' => '/evil',
    ];
});

test('forwarded host, port and prefix headers do not change the links of the authentication pages', function (string $page, string $linkedPage) {
    $this->get('http://opencongregationhelper.example.test'.$page, $this->spoofedHeaders)
        ->assertOk()
        ->assertSee('href="http://opencongregationhelper.example.test'.$linkedPage.'"', escape: false)
        ->assertSee('data-update-uri="http://opencongregationhelper.example.test/livewire-', escape: false)
        ->assertDontSee('evil.example', escape: false)
        ->assertDontSee(':8443', escape: false)
        ->assertDontSee('/evil/', escape: false);
})->with([
    'login page' => ['/app/login', '/app/password-reset/request'],
    'password reset request page' => ['/app/password-reset/request', '/app/login'],
]);

test('forwarded host, port and prefix headers do not change the password reset link sent by email', function () {
    Route::get('/trusted-proxy-test/reset-password-url', fn (): string => Filament::getResetPasswordUrl(
        'reset-token',
        User::factory()->make(['email' => 'admin@example.test']),
    ));

    $resetPasswordUrl = $this->get('http://opencongregationhelper.example.test/trusted-proxy-test/reset-password-url', $this->spoofedHeaders)
        ->assertOk()
        ->getContent();

    expect($resetPasswordUrl)->toStartWith('http://opencongregationhelper.example.test/app/password-reset/reset?');
});

test('URLs use the HTTPS scheme without an explicit port when the proxy forwards an HTTPS request over port 80 (GH36)', function () {
    $this->get('http://opencongregationhelper.example.test/app/login', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertSee('href="https://opencongregationhelper.example.test/app/password-reset/request"', escape: false)
        ->assertSee('data-update-uri="https://opencongregationhelper.example.test/livewire-', escape: false)
        ->assertDontSee('http://opencongregationhelper.example.test', escape: false)
        ->assertDontSee('opencongregationhelper.example.test:', escape: false);
});

test('the client IP is the right-most address of the forwarded chain', function (?string $forwardedFor, string $expectedIp) {
    Route::get('/trusted-proxy-test/client-ip', fn (Request $request): ?string => $request->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->get('/trusted-proxy-test/client-ip', array_filter(['X-Forwarded-For' => $forwardedFor]))
        ->assertOk()
        ->assertContent($expectedIp);
})->with([
    'no forwarded header' => [null, '10.0.0.1'],
    'address appended by the proxy' => ['5.6.7.8', '5.6.7.8'],
    // A client may prepend any address, but the proxy appends the one it received the request from
    'address supplied by the client before the proxy one' => ['1.2.3.4, 5.6.7.8', '5.6.7.8'],
]);

test('only the configured proxies can set the client IP', function (?string $trustedProxies, string $peer, string $expectedIp) {
    config(['trustedproxy.proxies' => $trustedProxies]);
    Route::get('/trusted-proxy-test/client-ip', fn (Request $request): ?string => $request->ip());

    $this->withServerVariables(['REMOTE_ADDR' => $peer])
        ->get('/trusted-proxy-test/client-ip', ['X-Forwarded-For' => '5.6.7.8'])
        ->assertOk()
        ->assertContent($expectedIp);
})->with([
    'any peer, by default' => ['*', '10.0.0.1', '5.6.7.8'],
    'the configured proxy' => ['10.0.0.2', '10.0.0.2', '5.6.7.8'],
    'a proxy in the configured range' => ['192.168.0.0/16, 10.0.0.0/8', '10.0.0.3', '5.6.7.8'],
    'a peer that is not the configured proxy' => ['10.0.0.2', '10.0.0.1', '10.0.0.1'],
    'any peer, when no proxy is configured' => [null, '10.0.0.1', '10.0.0.1'],
]);

test('trusts every peer when TRUSTED_PROXIES is not set, and none when it is empty', function (?string $value, ?string $expected) {
    $previous = getenv('TRUSTED_PROXIES');

    if ($value === null) {
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    } else {
        putenv('TRUSTED_PROXIES='.$value);
        $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $value;
    }

    try {
        expect((require config_path('trustedproxy.php'))['proxies'])->toBe($expected);
    } finally {
        $previous === false ? putenv('TRUSTED_PROXIES') : putenv('TRUSTED_PROXIES='.$previous);
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    }
})->with([
    'not set' => [null, '*'],
    'empty' => ['', null],
    'an address' => ['10.0.0.2', '10.0.0.2'],
]);
