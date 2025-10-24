<?php


use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::prefix('game')->group(function () {
    Route::post('/create', [GameController::class, 'create']);
    Route::post('/join', [GameController::class, 'join']);
    Route::post('/shoot', [GameController::class, 'shoot']);
    Route::post('/place-ships', [GameController::class, 'placeShips']);
    Route::post('/ability', [GameController::class, 'useAbility']);
    Route::post('/placement/random', [GameController::class, 'randomPlacement']);
    Route::get('/state/{player}', [GameController::class, 'state']);
});
