<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

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

Route::prefix('api')->middleware([
    'api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    Route::get('whoami', function () {
        return response()->json(['tenant_id' => tenant('id'), 'tenant_name' => tenant('name')]);
    });

    Route::prefix('v1/auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::middleware(['auth:sanctum', 'ensure.tenant'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });
    });

    Route::middleware(['auth:sanctum', 'ensure.tenant'])->prefix('v1')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('vehicles', VehicleController::class);
        Route::get('vehicles/{id}/reservations', [VehicleController::class, 'reservations']);
        Route::patch('vehicles/{id}/location', [VehicleController::class, 'updateLocation']);
        Route::get('reservations/user/{userId}', [ReservationController::class, 'byUser']);
        Route::patch('reservations/{id}/status', [ReservationController::class, 'updateStatus']);
        Route::apiResource('reservations', ReservationController::class);
        Route::get('tickets/user/{userId}', [TicketController::class, 'byUser']);
        Route::patch('tickets/{id}/assign', [TicketController::class, 'assign']);
        Route::patch('tickets/{id}/status', [TicketController::class, 'updateStatus']);
        Route::apiResource('tickets', TicketController::class);
        Route::apiResource('geofences', GeofenceController::class);
        Route::get('geofences/{id}/logs', [GeofenceController::class, 'logs']);
        Route::post('geofences/check-vehicle', [GeofenceController::class, 'checkVehicle']);
    });
});
