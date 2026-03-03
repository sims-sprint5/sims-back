<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\GeofenceController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| All routes here are tenant-aware. They are initialized with the tenant
| determined by the subdomain. For example:
|   empresa1.localhost/api/v1/users → Tenant: empresa1
|   empresa2.localhost/api/v1/users → Tenant: empresa2
|
*/

Route::middleware([
    'api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // ============================
    // AUTHENTICATION ROUTES
    // ============================
    Route::prefix('v1/auth')->group(function () {
        // Public routes (no authentication)
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // Protected routes (require token)
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });
    });

    // ============================
    // API ROUTES (PROTECTED)
    // ============================
    Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

        // Users
        Route::apiResource('users', UserController::class);

        // Vehicles
        Route::apiResource('vehicles', VehicleController::class);
        Route::get('vehicles/{id}/reservations', [VehicleController::class, 'reservations']);
        Route::patch('vehicles/{id}/location', [VehicleController::class, 'updateLocation']);

        // Reservations
        Route::get('reservations/user/{userId}', [ReservationController::class, 'byUser']);
        Route::patch('reservations/{id}/status', [ReservationController::class, 'updateStatus']);
        Route::apiResource('reservations', ReservationController::class);

        // Tickets
        Route::get('tickets/user/{userId}', [TicketController::class, 'byUser']);
        Route::patch('tickets/{id}/assign', [TicketController::class, 'assign']);
        Route::patch('tickets/{id}/status', [TicketController::class, 'updateStatus']);
        Route::apiResource('tickets', TicketController::class);

        // Geofences
        Route::apiResource('geofences', GeofenceController::class);
        Route::get('geofences/{id}/logs', [GeofenceController::class, 'logs']);
        Route::post('geofences/check-vehicle', [GeofenceController::class, 'checkVehicle']);
    });
});
