<?php

use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // Render the real assets when they are built (as in CI), and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

function assertSecurityHeaders(TestResponse $response): void
{
    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('X-Powered-By');
}

it('sends the security headers on the web pages', function () {
    assertSecurityHeaders($this->get('/app/login')->assertOk());
});

it('sends the security headers on the API and on redirects', function () {
    assertSecurityHeaders($this->getJson('/api/artisan/list')->assertUnauthorized());
    assertSecurityHeaders($this->get('/dashboard')->assertRedirect());
});

it('sends the security headers on the error pages', function () {
    assertSecurityHeaders($this->get('/this-page-does-not-exist')->assertNotFound());
});

it('removes the X-Powered-By header and keeps the headers a response sets itself', function () {
    Route::get('/security-headers-test', fn () => response('ok')
        ->header('X-Powered-By', 'PHP/8.2.0')
        ->header('X-Frame-Options', 'SAMEORIGIN'));

    $this->get('/security-headers-test')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', SetSecurityHeaders::HEADERS['X-Content-Type-Options'])
        ->assertHeaderMissing('X-Powered-By');
});
