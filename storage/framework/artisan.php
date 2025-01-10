<?php

use App\Http\Controllers\ArtisanCommandController;
use Illuminate\Support\Facades\Route;

Route::domain('artisan.' . preg_replace("/(^https?:\/\/|:.*$|\/\s*$)/", "", config('app.url')))->controller(ArtisanCommandController::class)->group(function () {
    Route::post('/execute/{command?}', 'execute')
        ->name('execute_artisan_command');
    Route::get('/list', 'list')
        ->name('list_artisan_commands');
})->withoutMiddleware();