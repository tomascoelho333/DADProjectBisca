<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\MatchController;
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
 *  Game Related Routes
 *
 */
Route::prefix('games')->group(function () {
    //Protected Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/test', function(Request $request) {
            return response()->json(['message' => 'Test route works', 'user' => $request->user()], 200);
        });
        Route::get('/', [GameController::class, 'index']); // List available games
        Route::post('/', [GameController::class, 'store']); // Create new game
        Route::get('/history', [GameController::class, 'history']); // User's game history
        Route::get('/{game}', [GameController::class, 'show']); // Get game details
        Route::post('/{game}/join', [GameController::class, 'join']); // Join game
        Route::post('/{game}/move', [GameController::class, 'makeMove']); // Make a move
    });

    // Public routes for anonymous users (single-player only)
    Route::post('/anonymous', [GameController::class, 'createAnonymousGame']);
    Route::get('/anonymous/{game}', [GameController::class, 'showAnonymousGame']);
    Route::post('/anonymous/{game}/move', [GameController::class, 'makeAnonymousMove']);
});

/*
 *
 *  Match Related Routes
 *
 */
Route::prefix('matches')->group(function () {
    //Protected Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [MatchController::class, 'index']); // List available matches
        Route::post('/', [MatchController::class, 'store']); // Create new match
        Route::get('/history', [MatchController::class, 'history']); // User's match history
        Route::get('/{match}', [MatchController::class, 'show']); // Get match details
        Route::post('/{match}/join', [MatchController::class, 'join']); // Join match
        Route::post('/{match}/resign', [MatchController::class, 'resign']); // Resign from match
    });
});
