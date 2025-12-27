<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
 *
 *  Auth Related Routes
 *
 */
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    //Protected Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/*
 *
 *  User Related Routes
 *
 */
Route::prefix('users')->group(function () {
    //Protected Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('me', [UserController::class, 'show']);
        // Accepts both put and post, makes so photo uploads work correctly
        Route::match(['put', 'post'], 'me', [UserController::class, 'update']);
        // User Deletion
        Route::delete('me', [UserController::class, 'destroy']);
    });
});

/*
 *
 * Statistics Related Routes
 *
 */
Route::prefix('statistics')->group(function () {
    Route::get('/', [StatisticsController::class, 'index']);
    // LOGGED + ADMIN
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('advanced', [StatisticsController::class, 'adminStats']);
    });
});
