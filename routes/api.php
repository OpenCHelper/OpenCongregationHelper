<?php

use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\TerritoryZoneController;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Load special routes for the Artisan command execution
require base_path('routes/api/artisan.php');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('territories', TerritoryController::class)->only(['index', 'show']);
    Route::apiResource('zones', TerritoryZoneController::class)->only(['index', 'show']);

    Route::get('/assignees', function () {
        return User::all();
    })->middleware('can:viewAny,'.User::class);

    Route::get('/assignments', function () {
        return TerritoryAssignment::all();
    })->middleware('can:viewAny,'.TerritoryAssignment::class);
});
