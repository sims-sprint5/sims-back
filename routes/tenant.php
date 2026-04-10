<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketMessageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::prefix('api')->middleware([
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
    'api',
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
            Route::patch('me', [AuthController::class, 'updateMe']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });
    });

    Route::middleware(['auth:sanctum', 'ensure.tenant'])->prefix('v1')->group(function () {
        // Admin-only: user management
        Route::middleware(['role:Admin,sanctum'])->group(function () {
            Route::apiResource('users', UserController::class);
        });

        // Vehicles: everyone can view, only Admin can mutate/manage
        Route::apiResource('vehicles', VehicleController::class)->only(['index', 'show']);
        Route::middleware(['role:Admin,sanctum'])->group(function () {
            Route::apiResource('vehicles', VehicleController::class)->only(['store', 'update', 'destroy']);
            Route::get('vehicles/{id}/reservations', [VehicleController::class, 'reservations']);
            Route::patch('vehicles/{id}/location', [VehicleController::class, 'updateLocation']);
        });

        // Reservations: both roles can use resource endpoints, but Usuario is scoped to own reservations in controller
        Route::get('reservations/user/{userId}', [ReservationController::class, 'byUser']);
        Route::get('reservations/check-availability', [ReservationController::class, 'checkAvailability']);
        Route::apiResource('reservations', ReservationController::class);
        Route::post('reservations/{id}/renewal-intent', [ReservationController::class, 'renewalIntent']);
        Route::middleware(['role:Admin,sanctum'])->group(function () {
            Route::patch('reservations/{id}/status', [ReservationController::class, 'updateStatus']);
        });

        // Tickets: Usuario can view/create own; Admin can manage/assign/status
        Route::get('tickets/user/{userId}', [TicketController::class, 'byUser']);
        Route::apiResource('tickets', TicketController::class)->only(['index', 'show', 'store']);

        // Ticket messages: history + send message
        Route::get('tickets/{ticket}/messages', [TicketMessageController::class, 'index']);
        Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store']);

        Route::middleware(['role:Admin,sanctum'])->group(function () {
            Route::apiResource('tickets', TicketController::class)->only(['update', 'destroy']);
            Route::patch('tickets/{id}/assign', [TicketController::class, 'assign']);
            Route::patch('tickets/{id}/status', [TicketController::class, 'updateStatus']);
        });

        // Geofencing: everyone can view (map), only Admin can mutate/manage
        Route::apiResource('geofences', GeofenceController::class)->only(['index', 'show']);
        Route::middleware(['role:Admin,sanctum'])->group(function () {
            Route::apiResource('geofences', GeofenceController::class)->only(['store', 'update', 'destroy']);
            Route::get('geofences/{id}/logs', [GeofenceController::class, 'logs']);
            Route::post('geofences/check-vehicle', [GeofenceController::class, 'checkVehicle']);
        });
    });
});
