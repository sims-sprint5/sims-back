<?php

namespace App\Modules\Incidences\Policies;

use App\Modules\Incidences\Models\Incidence;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;

class IncidencePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Incidence $incidence): bool
    {
        // Admin and workers can view all incidences
        if ($user->hasPermissionTo('incidences.view.all')) {
            return true;
        }

        // User can only view their own incidences
        return $user->id === $incidence->reported_by;
    }

    public function create(User $user): bool
    {
        // Any authenticated user can create incidences
        return true;
    }

    public function update(User $user, Incidence $incidence): Response
    {
        // Admin and workers can update any incidence
        if ($user->hasPermissionTo('incidences.manage.all')) {
            return Response::allow();
        }

        // User can update only their own incidences
        if ($user->id === $incidence->reported_by) {
            return Response::allow();
        }

        return Response::deny('You cannot update this incidence.');
    }

    public function delete(User $user, Incidence $incidence): bool
    {
        // Only admin can permanently delete incidences
        return $user->hasPermissionTo('incidences.destroy.all');
    }

    public function resolve(User $user, Incidence $incidence): bool
    {
        // Admin and workers can resolve incidences
        return $user->hasPermissionTo('incidences.manage.all');
    }
}
