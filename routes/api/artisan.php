<?php

use App\Http\Controllers\ArtisanCommandController;
use App\Http\Controllers\ArtisanQueueController;
use App\Http\Middleware\EnsureArtisanToken;
use Illuminate\Support\Facades\Route;

Route::domain(
    'artisan.' . preg_replace("/(^https?:\/\/|:.*$|\/\s*$)/", "", config('app.url'))
)->group(function () {
    // Protected artisan commands
    Route::controller(ArtisanCommandController::class)->middleware(EnsureArtisanToken::class)->group(function () {
        Route::post('/execute/{command?}', 'execute')
            ->name('execute');
        Route::get('/list', 'list')
            ->name('list');
    });

    // Queue processing
    Route::post('/queue/process', ArtisanQueueController::class)
        ->name('queue.process');
})->name('artisan.');