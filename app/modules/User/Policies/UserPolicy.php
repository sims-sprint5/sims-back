<?php

namespace App\Modules\User\Policies;

use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view.all');
    }

    public function view(User $user, User $targetUser): bool
    {
        // Admin ve todos
        if ($user->hasPermissionTo('users.view.all')) {
            return true;
        }

        // Usuario ve solo su propio perfil
        return $user->id === $targetUser->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.manage.all');
    }

    public function update(User $user, User $targetUser): Response
    {
        // Admin puede editar cualquiera
        if ($user->hasPermissionTo('users.manage.all')) {
            return Response::allow();
        }

        // Usuario puede editar solo su propio perfil
        if ($user->id === $targetUser->id) {
            return Response::allow();
        }

        return Response::deny('You cannot update this user.');
    }

    public function delete(User $user, User $targetUser): bool
    {
        // Usuario puede eliminar su propio perfil
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Admin puede eliminar a otros
        if ($user->hasPermissionTo('users.destroy.all')) {
            return true;
        }

        return false;
    }
}
