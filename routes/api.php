<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketMessageController;
use App\Http\Controllers\Api\GeofenceController;

// ============================
// RUTAS DE AUTENTICACIÓN
// ============================
Route::get('/ping', function() {
    return response()->json(['message' => 'API is working']);
});
Route::prefix('v1/auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    
    Route::apiResource('users', UserController::class);

    Route::get('vehicles/{id}/reservations', [VehicleController::class, 'reservations'])->whereNumber('id');
    Route::patch('vehicles/{id}/location', [VehicleController::class, 'updateLocation'])->whereNumber('id');
    Route::apiResource('vehicles', VehicleController::class);

    Route::get('reservations/user/{userId}', [ReservationController::class, 'byUser'])->whereNumber('userId');
    Route::patch('reservations/{id}/status', [ReservationController::class, 'updateStatus'])->whereNumber('id');
    Route::apiResource('reservations', ReservationController::class);

    Route::get('tickets/user/{userId}', [TicketController::class, 'byUser'])->whereNumber('userId');
    Route::patch('tickets/{id}/assign', [TicketController::class, 'assign'])->whereNumber('id');
    Route::patch('tickets/{id}/status', [TicketController::class, 'updateStatus'])->whereNumber('id');
    Route::get('tickets/{ticket}/messages', [TicketMessageController::class, 'index'])->whereNumber('ticket');
    Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->whereNumber('ticket');
    Route::apiResource('tickets', TicketController::class);

    Route::post('geofences/check-vehicle', [GeofenceController::class, 'checkVehicle']);
    Route::get('geofences/{id}/logs', [GeofenceController::class, 'logs'])->whereNumber('id');
    Route::apiResource('geofences', GeofenceController::class);
});

