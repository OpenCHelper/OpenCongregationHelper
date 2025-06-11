<?php

use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
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

Route::redirect('/login', '/app/login')->name('login');

Route::redirect('/register', '/app/register')->name('register');

Route::redirect('/dashboard', '/app')->name('dashboard');

Route::get('/team-invitations/{invitation}', [TeamInvitationController::class, 'accept'])
    ->middleware(['signed', 'verified', 'auth', AuthenticateSession::class])
    ->name('team-invitations.accept');

// Share route that redirects to Filament Guest panel
Route::get('/share/{uuid}', function ($uuid) {
    $sharedUrl = App\Models\SharedUrl::where('uid', $uuid)->first();
    
    if (!$sharedUrl || !$sharedUrl->shareable) {
        abort(404, 'Shared resource not found.');
    }
    
    // Check if the shareable model has a guest view page trait
    if (method_exists($sharedUrl->shareable, 'getGuestPageRoute')) {
        $redirectPath = $sharedUrl->shareable->getGuestPageRoute() . "/{$uuid}";
    } else {
        // Use the generic shared resource page as fallback
        $redirectPath = "/guest/shared-resource/{$uuid}";
    }
    
    return redirect($redirectPath);
})->name('share.redirect');
