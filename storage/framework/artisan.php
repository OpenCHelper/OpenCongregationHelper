<?php

use App\Http\Controllers\ExecuteArtisanCommandController;
use Illuminate\Support\Facades\Route;

Route::domain('artisan.' . preg_replace("/(^https?:\/\/|:.*$)/", "", config('app.url')))->group(function () {
    Route::post('/execute/{command}', ExecuteArtisanCommandController::class)
        ->name('execute_artisan_command');
})->withoutMiddleware();