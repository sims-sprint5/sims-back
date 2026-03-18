<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        // API requests must never redirect to a web login route.
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        // Only redirect if a named login route actually exists.
        if (Route::has('login')) {
            return route('login');
        }

        return null;
    }
}
