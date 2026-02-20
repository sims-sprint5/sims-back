<?php

use App\Modules\Incidences\Controllers\IncidenceController;
use Illuminate\Support\Facades\Route;

// Incidences CRUD routes
Route::get('/incidences', [IncidenceController::class, 'index'])->name('incidences.index');
Route::post('/incidences', [IncidenceController::class, 'store'])->name('incidences.store');
Route::get('/incidences/{id}', [IncidenceController::class, 'show'])->name('incidences.show');
Route::put('/incidences/{id}', [IncidenceController::class, 'update'])->name('incidences.update');
Route::patch('/incidences/{id}', [IncidenceController::class, 'update'])->name('incidences.update');
Route::delete('/incidences/{id}', [IncidenceController::class, 'destroy'])->name('incidences.destroy');

// Trashed incidences
Route::get('/incidences-trashed', [IncidenceController::class, 'trashed'])->name('incidences.trashed');
