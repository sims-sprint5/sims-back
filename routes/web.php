<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $host = $request->getHost();
    $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);

    if (in_array($host, $centralDomains)) {
        return response()->json([
            'message' => 'SIMS API',
            'status' => 'ok',
        ]);
    }

    return response()->json(['message' => 'Not found.'], 404);
});
