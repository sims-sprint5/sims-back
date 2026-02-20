<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyInitialized;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TenancyInitialized::class, function (TenancyInitialized $event) {
            // Resetear caché de permisos para el tenant
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
