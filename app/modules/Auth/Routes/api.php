<?php

use App\Http\Middleware\AuthenticateTenant;
use App\Modules\Auth\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware(AuthenticateTenant::class)->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
});
