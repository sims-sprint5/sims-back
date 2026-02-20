<?php

use App\Modules\Vehicle\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

// Tenant Vehicles CRUD routes
Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
Route::get('/vehicles/{vehicleId}', [VehicleController::class, 'show'])->name('vehicles.show');
Route::match(['put', 'patch'], '/vehicles/{vehicleId}', [VehicleController::class, 'update'])->name('vehicles.update');
Route::delete('/vehicles/{vehicleId}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
