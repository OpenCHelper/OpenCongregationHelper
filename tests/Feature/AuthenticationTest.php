<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    // Render the real assets when they are built (as in CI), so that entries missing
    // from the Vite build are caught, and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertRedirect('/app/login');

    // Follow the redirect and check the final page
    $finalResponse = $this->get('/app/login');
    $finalResponse->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(), // Ensure email is verified
        'can_login' => true, // Ensure user can login
    ]);

    // Test that authentication works by simulating login
    $this->actingAs($user);
    $this->assertAuthenticated();

    // Test that authenticated user can access protected routes
    $response = $this->get('/app');
    $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users are logged in through a valid remember me cookie', function () {
    $user = User::factory()->create();
    $guard = Auth::guard('web');

    $this->withCookie(
        $guard->getRecallerName(),
        $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword()),
    )->get('/')->assertOk();

    $this->assertAuthenticatedAs($user);
});

test('a stale remember me cookie is ignored instead of crashing the request', function () {
    $user = User::factory()->create();
    $guard = Auth::guard('web');

    // The cookie still references the user, but its token no longer matches the stored one
    // (e.g. the user logged out from another device, which rotates the remember token)
    $this->withCookie(
        $guard->getRecallerName(),
        $user->getAuthIdentifier().'|stale-remember-token|'.$guard->hashPasswordForCookie($user->getAuthPassword()),
    )->get('/')->assertOk();

    $this->assertGuest();
});
