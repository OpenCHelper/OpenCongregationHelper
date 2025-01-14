<?php

use App\Http\Controllers\ArtisanCommandController;
use App\Http\Middleware\EnsureArtisanToken;
use Illuminate\Support\Facades\Route;

Route::domain(
    'artisan.' . preg_replace("/(^https?:\/\/|:.*$|\/\s*$)/", "", config('app.url'))
)->middleware(EnsureArtisanToken::class)->controller(ArtisanCommandController::class)->group(function () {
    Route::post('/execute/{command?}', 'execute')
        ->name('execute');
    Route::get('/list', 'list')
        ->name('list');
})->name('artisan.');