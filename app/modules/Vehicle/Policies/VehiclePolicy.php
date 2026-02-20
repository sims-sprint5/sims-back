<?php

namespace App\Modules\Vehicle\Policies;

use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Auth\Access\Response;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('vehicles.view.all');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermissionTo('vehicles.view.all');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vehicles.manage.all');
    }

    public function update(User $user, Vehicle $vehicle): Response
    {
        if ($user->hasPermissionTo('vehicles.manage.all')) {
            return Response::allow();
        }

        return Response::deny('You cannot update this vehicle.');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermissionTo('vehicles.destroy.all');
    }
}
