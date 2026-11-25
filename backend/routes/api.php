<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RoomController;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => $request->user());

    Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);

    Route::middleware('admin')->group(function () {
        Route::apiResource('rooms', RoomController::class)->only(['store', 'update', 'destroy']);
    });
});
