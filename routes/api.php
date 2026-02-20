<?php

use App\Http\Middleware\AuthenticateTenant;
use App\Http\Middleware\InitializeTenantFromHeader;
use Illuminate\Support\Facades\Route;

Route::prefix('central')->group(function () {
    require base_path('app/modules/Central/Routes/api.php');
});

Route::prefix('tenant')
    ->middleware(['api', InitializeTenantFromHeader::class])
    ->group(function () {
        require base_path('app/modules/Auth/Routes/api.php');

        Route::middleware(AuthenticateTenant::class)->group(function () {
            require base_path('app/modules/User/Routes/api.php');
            require base_path('app/modules/Vehicle/Routes/api.php');
            require base_path('app/modules/Incidences/Routes/api.php');
        });
    });
