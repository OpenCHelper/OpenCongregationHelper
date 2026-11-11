<?php

use App\Http\Controllers\ArtisanCommandController;
use App\Http\Controllers\ArtisanQueueController;
use App\Http\Controllers\ArtisanScheduledController;
use App\Http\Middleware\EnsureArtisanToken;
use App\Http\Middleware\EnsureCronToken;
use Illuminate\Support\Facades\Route;

Route::domain(
    'artisan.'.preg_replace("/(^https?:\/\/|:.*$|\/\s*$)/", '', config('app.url'))
)->name('artisan.')->group(function () {
    // Protected artisan commands
    Route::controller(ArtisanCommandController::class)->middleware(EnsureArtisanToken::class)->group(function () {
        Route::post('/execute/{command?}', 'execute')
            ->name('execute');
        Route::get('/list', 'list')
            ->name('list');
    });

    // Endpoints triggered by external cron services (shared hosting has no cron)
    Route::middleware([EnsureCronToken::class, 'throttle:10,1'])->group(function () {
        // Queue processing
        Route::post('/queue/process', ArtisanQueueController::class)
            ->name('queue.process');

        // Cron (scheduled) jobs processing
        Route::post('/schedule/process', ArtisanScheduledController::class)
            ->name('schedule.process');
    });
});
