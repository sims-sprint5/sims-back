<?php

use App\modules\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Tenant Users CRUD routes (using userId instead of automatic model binding)
Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::get('/users/{userId}', [UserController::class, 'show'])->name('users.show');
Route::put('/users/{userId}', [UserController::class, 'update'])->name('users.update');
Route::patch('/users/{userId}', [UserController::class, 'update'])->name('users.update');
Route::delete('/users/{userId}', [UserController::class, 'destroy'])->name('users.destroy');
