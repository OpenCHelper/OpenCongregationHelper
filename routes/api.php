<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\TerritoryZoneController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('territories', TerritoryController::class);
Route::apiResource('zones', TerritoryZoneController::class);