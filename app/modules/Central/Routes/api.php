<?php

use App\Modules\Central\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:central');
Route::get('me', [AuthController::class, 'me'])->middleware('auth:central');
