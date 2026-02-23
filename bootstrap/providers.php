<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    App\Modules\User\Providers\UserServiceProvider::class,
    App\Modules\Vehicle\Providers\VehicleServiceProvider::class,
    App\Modules\Incidences\Providers\IncidenceServiceProvider::class,
];
