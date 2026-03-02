<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Central\SuperAdminAuthController;
use App\Http\Controllers\Central\TenantController;

// Health check
Route::get('/ping', function () {
    return response()->json(['message' => 'API is working']);
});

// Auth Superadmin (public)
Route::prefix('v1/superadmin/auth')->group(function () {
    Route::post('login', [SuperAdminAuthController::class, 'login']);
});

// Protected Superadmin routes
Route::middleware('auth:superadmin')->prefix('v1/superadmin')->group(function () {
    Route::get('auth/me',         [SuperAdminAuthController::class, 'me']);
    Route::post('auth/logout',    [SuperAdminAuthController::class, 'logout']);

    Route::get('tenants',         [TenantController::class, 'index']);
    Route::post('tenants',        [TenantController::class, 'store']);
    Route::delete('tenants/{id}', [TenantController::class, 'destroy']);
});
