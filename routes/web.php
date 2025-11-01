<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\StatsController;
use App\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::view('/game', 'app');

Route::prefix('api')->middleware([SetLocaleFromRequest::class])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
        Route::get('/me', [AuthController::class, 'me'])->middleware('auth');
    });

    Route::middleware(['auth', 'throttle:game'])->prefix('game')->group(function () {
        Route::post('/create', [GameController::class, 'create']);
        Route::post('/bot', [GameController::class, 'createBot']);
        Route::post('/join', [GameController::class, 'join']);
        Route::post('/shoot', [GameController::class, 'shoot']);
        Route::post('/place-ships', [GameController::class, 'placeShips']);
        Route::post('/ability', [GameController::class, 'useAbility']);
        Route::post('/placement/random', [GameController::class, 'randomPlacement']);
        Route::post('/rematch', [GameController::class, 'rematch']);
        Route::get('/state/{player}', [GameController::class, 'state'])->whereNumber('player');
        Route::post('/leave/{player}', [GameController::class, 'leaveGame'])->whereNumber('player');
        Route::get('/available', [GameController::class, 'getAvailableGames']);
    });

    Route::middleware(['auth', 'throttle:60,1'])->prefix('stats')->group(function () {
        Route::get('/leaderboard', [StatsController::class, 'leaderboard']);
    });
});