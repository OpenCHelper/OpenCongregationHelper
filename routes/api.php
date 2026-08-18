<?php

use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\TerritoryZoneController;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Load special routes for the Artisan command execution
require base_path('routes/api/artisan.php');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('territories', TerritoryController::class)->middleware('auth:sanctum');
Route::apiResource('zones', TerritoryZoneController::class)->middleware('auth:sanctum');
Route::get('/assignees', function () {
    return User::all();
})->middleware('auth:sanctum');
Route::get('/assignments', function () {
    return TerritoryAssignment::all();
})->middleware('auth:sanctum');
