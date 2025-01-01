<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\TerritoryZoneController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('territories', TerritoryController::class)->middleware('auth:sanctum');
Route::apiResource('zones', TerritoryZoneController::class)->middleware('auth:sanctum');
Route::get('/assignees', function () {
    return App\Models\Assignee::all();
})->middleware('auth:sanctum');
Route::get('/assignments', function () {
    return App\Models\AssignmentHistory::all();
})->middleware('auth:sanctum');