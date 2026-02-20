<?php

namespace App\Modules\Incidences\Providers;

use App\Modules\Incidences\Models\Incidence;
use App\Modules\Incidences\Policies\IncidencePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IncidenceServiceProvider extends ServiceProvider
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
        Gate::policy(Incidence::class, IncidencePolicy::class);
    }
}
