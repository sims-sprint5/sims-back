<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$isCentralHost = static function (Request $request): bool {
    $host = strtolower($request->getHost());
    $centralDomains = (array) config('tenancy.central_domains', ['localhost', '127.0.0.1']);
    $appDomain = (string) config('app.domain', '');
    $appUrlHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);

    if ($appDomain !== '') {
        $centralDomains[] = $appDomain;
    }

    if (is_string($appUrlHost) && $appUrlHost !== '') {
        $centralDomains[] = $appUrlHost;
    }

    $normalized = [];

    foreach ($centralDomains as $domain) {
        if (! is_string($domain) || $domain === '') {
            continue;
        }

        $cleanDomain = strtolower(preg_replace('#^https?://#', '', $domain));
        $cleanDomain = explode(':', $cleanDomain)[0] ?? $cleanDomain;
        $cleanDomain = explode('/', $cleanDomain)[0] ?? $cleanDomain;

        if ($cleanDomain === '') {
            continue;
        }

        $normalized[] = $cleanDomain;

        if (! str_starts_with($cleanDomain, 'www.')) {
            $normalized[] = 'www.'.$cleanDomain;
        }
    }

    return in_array($host, array_values(array_unique($normalized)), true);
};

Route::get('/welcome', function (Request $request) use ($isCentralHost) {
    if ($isCentralHost($request)) {
        return view('welcome');
    }

    abort(404);
});

Route::get('/', function (Request $request) use ($isCentralHost) {
    if ($isCentralHost($request)) {
        return redirect('/welcome');
    }

    abort(404);
});
