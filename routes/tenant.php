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
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });
});

Route::prefix('v1/auth')->group(function () {
    // Rutas públicas (sin autenticación)
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Rutas protegidas (requieren autenticación)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

// ============================
// RUTAS DE LA API (PROTEGIDAS)
// ============================
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // Users - CRUD completo
    Route::apiResource('users', UserController::class);

    // Vehicles - CRUD completo
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicles/{id}/reservations', [VehicleController::class, 'reservations']);
    Route::patch('vehicles/{id}/location', [VehicleController::class, 'updateLocation']);

    // Reservations - CRUD completo
    Route::apiResource('reservations', ReservationController::class);
    Route::get('reservations/user/{userId}', [ReservationController::class, 'byUser']);
    Route::patch('reservations/{id}/status', [ReservationController::class, 'updateStatus']);

    // Tickets - CRUD completo
    Route::apiResource('tickets', TicketController::class);
    Route::get('tickets/user/{userId}', [TicketController::class, 'byUser']);
    Route::patch('tickets/{id}/assign', [TicketController::class, 'assign']);
    Route::patch('tickets/{id}/status', [TicketController::class, 'updateStatus']);

    // Geofences - CRUD completo
    Route::apiResource('geofences', GeofenceController::class);
    Route::get('geofences/{id}/logs', [GeofenceController::class, 'logs']);
    Route::post('geofences/check-vehicle', [GeofenceController::class, 'checkVehicle']);
});


