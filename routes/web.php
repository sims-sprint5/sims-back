<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Only serve the welcome page on exact central domains (localhost, 127.0.0.1, lvh.me).
// Tenant subdomains (empresa1.lvh.me, etc.) are API-only – return 404 JSON.
Route::get('/', function (Request $request) {
    $host = $request->getHost(); // e.g. "empresa1.lvh.me" or "localhost"
    $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);

    // Serve the welcome page only if the host is exactly a central domain
    if (in_array($host, $centralDomains)) {
        return view('welcome');
    }

    // Tenant subdomain – the frontend SPA handles routing; the API is at /api/*
    return response()->json(['message' => 'Not found.'], 404);
});
