<?php

use App\Http\Controllers\AuthController;
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
        Route::post('me', [UserController::class, 'update']);
        // User Deletion
        Route::delete('me', [UserController::class, 'destroy']);
    });
});
