<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$isCentralHost = static function (Request $request): bool {
    $host = $request->getHost();
    $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);

    return in_array($host, $centralDomains, true);
};

Route::get('/', function (Request $request) use ($isCentralHost) {
    if ($isCentralHost($request)) {
        return view('welcome');
    }

    abort(404);
});
