<?php

use App\Http\Controllers\Central\SuperAdminAuthController;
use App\Http\Controllers\Central\SuperAdminController;
use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});

// Health check
Route::get('/ping', fn () => response()->json(['status' => 'ok', 'timestamp' => now()]));

// SuperAdmin public auth routes
Route::prefix('v1/superadmin/auth')->group(function () {
    Route::post('login', [SuperAdminAuthController::class, 'login']);
});

// SuperAdmin protected routes
Route::middleware(['auth:sanctum', 'ensure.superadmin'])->prefix('v1/superadmin')->group(function () {
    // Auth
    Route::get('auth/me', [SuperAdminAuthController::class, 'me']);
    Route::post('auth/logout', [SuperAdminAuthController::class, 'logout']);

    // SuperAdmins
    Route::get('admins', [SuperAdminController::class, 'index']);
    Route::post('admins', [SuperAdminController::class, 'store']);
    Route::delete('admins/{id}', [SuperAdminController::class, 'destroy']);

    // Tenants
    Route::get('tenants', [TenantController::class, 'index']);
    Route::post('tenants', [TenantController::class, 'store']);
    Route::get('tenants/{id}', [TenantController::class, 'show']);
    Route::delete('tenants/{id}', [TenantController::class, 'destroy']);
});
