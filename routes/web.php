<?php

use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\WebManifestController;
use App\Http\Middleware\PreventSearchIndexing;
use App\Models\SharedUrl;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Http\Controllers\TeamInvitationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', fn () => view('welcome'));

// Progressive Web App: the manifest makes the app installable, the offline page is shown by the service worker
// (`public/sw.js`) when a page cannot be loaded without a connection.
Route::get('/manifest.webmanifest', WebManifestController::class)->name('web-manifest');
Route::view('/offline', 'offline')->name('offline');

Route::redirect('/login', '/app/login')->name('login');

// The panel has a registration page only while the registration is enabled (e.g. team invitations link here).
if (Features::enabled(Features::registration())) {
    Route::redirect('/register', '/app/register')->name('register');
}

// Files of the `public` disk, reached only when the web server cannot follow the `public/storage` link (e.g. Altervista)
Route::get('/storage/{path}', PublicStorageController::class)->where('path', '.*')->name('storage.public');

Route::redirect('/dashboard', '/app')->name('dashboard');

// Jetstream sends the user here after uploading a new profile photo, but the profile page is in the Filament panel.
Route::redirect('/user/profile', '/app/edit-profile')->name('profile.show');

// Users with an unverified email are sent to the verification of the panel, as Fortify's `verification.notice` route is not registered.
Route::get('/team-invitations/{invitation}', [TeamInvitationController::class, 'accept'])
    ->middleware(['signed', EnsureEmailIsVerified::redirectTo('filament.app.auth.email-verification.prompt'), 'auth', AuthenticateSession::class])
    ->name('team-invitations.accept');

// Share route that redirects to Filament Guest panel
Route::get('/share/{uuid}', function ($uuid) {
    abort_unless(SharedUrl::isSharingEnabled(), 404);

    $sharedUrl = SharedUrl::where('uid', $uuid)->first();

    if (! $sharedUrl || ! $sharedUrl->shareable) {
        abort(404, 'Shared resource not found.');
    }

    // Check if the shareable model has a guest view page trait
    if (method_exists($sharedUrl->shareable, 'getGuestPageRoute')) {
        $redirectPath = $sharedUrl->shareable->getGuestPageRoute()."/{$uuid}";
    } else {
        // Use the generic shared resource page as fallback
        $redirectPath = "/guest/shared-resource/{$uuid}";
    }

    return redirect($redirectPath);
})->middleware(PreventSearchIndexing::class)->name('share.redirect');
