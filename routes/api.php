<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\TerritoryZoneController;

// Load special routes for the Artisan command execution
require base_path('routes/api/artisan.php');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('territories', TerritoryController::class)->middleware('auth:sanctum');
Route::apiResource('zones', TerritoryZoneController::class)->middleware('auth:sanctum');
Route::get('/assignees', function () {
    return App\Models\User::all();
})->middleware('auth:sanctum');
Route::get('/assignments', function () {
    return App\Models\TerritoryAssignment::all();
})->middleware('auth:sanctum');