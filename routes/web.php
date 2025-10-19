<?php

use Illuminate\Support\Facades\Route;


Route::get('/', fn () => view('app'));
Route::get('/game', fn () => view('app'));
