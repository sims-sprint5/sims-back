<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// ============================
// RUTAS DE AUTENTICACIÓN
// ============================
Route::get('/ping', function() {
    return response()->json(['message' => 'API is working']);
});
