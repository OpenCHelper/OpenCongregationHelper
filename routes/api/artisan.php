<?php

use App\Http\Controllers\ArtisanCommandController;
use Illuminate\Support\Facades\Route;

define("ARTISAN_SUBDOMAIN_URL", 'artisan.' . preg_replace("/(^https?:\/\/|:.*$|\/\s*$)/", "", config('app.url')));

Route::domain(ARTISAN_SUBDOMAIN_URL)->controller(ArtisanCommandController::class)->group(function () {
    Route::post('/execute/{command?}', 'execute')
        ->name('execute');
    Route::get('/list', 'list')
        ->name('list');
})->name('artisan.');