<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Safety guard: in existing projects it's easy to run seeders before migrating,
        // or run them in the central context where tenant tables don't exist.
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users')) {
            return;
        }

        // permissions table is created by Spatie migrations
        if (! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Spatie roles/permissions for tenant Users must use the 'web' guard.
        // Using auth.defaults.guard (e.g. 'sanctum') would create guard_name
        // mismatches and break assignRole/hasRole checks.
        $guardName = 'web';

        $superAdminRole = Role::findOrCreate('Super Admin', $guardName);
        $adminRole = Role::findOrCreate('Admin', $guardName);
        $userRole = Role::findOrCreate('User', $guardName);

        // Permissions (coarse-grained, mapped to current endpoints)
        $permissions = [
            // Vehicles
            'vehicles.view',
            'vehicles.manage',
            'vehicles.reservations.view',
            'vehicles.location.update',

            // Reservations
            'reservations.view.own',
            'reservations.create.own',
            'reservations.update.own',
            'reservations.delete.own',
            'reservations.view.any',
            'reservations.status.update',

            // Tickets
            'tickets.view.own',
            'tickets.create.own',
            'tickets.view.any',
            'tickets.manage',
            'tickets.assign',
            'tickets.status.update',

            // Users
            'users.manage',

            // Geofences
            'geofences.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, $guardName);
        }

        // Assign permissions to roles (can be tightened later without changing endpoints)
        $userRole->syncPermissions([
            'vehicles.view',
            'reservations.view.own',
            'reservations.create.own',
            'reservations.update.own',
            'reservations.delete.own',
            'tickets.view.own',
            'tickets.create.own',
        ]);

        $adminRole->syncPermissions([
            'vehicles.view',
            'vehicles.manage',
            'vehicles.reservations.view',
            'vehicles.location.update',
            'reservations.view.any',
            'reservations.status.update',
            'tickets.view.any',
            'tickets.manage',
            'tickets.assign',
            'tickets.status.update',
            'users.manage',
            'geofences.manage',
        ]);

        // Super Admin role is reserved (central system uses a different auth model today).
        // Keep it permissive by default if it's used in the future.
        $superAdminRole->syncPermissions(Permission::query()->where('guard_name', $guardName)->pluck('name')->all());

        // Backfill roles for existing users based on legacy users.role column.
        // This avoids breaking existing deployments when role middleware is enabled.
        foreach (User::query()->orderBy('user_id')->cursor() as $user) {
            /** @var User $user */
            if ($user->roles()->exists()) {
                continue;
            }

            $legacyRole = strtolower((string) ($user->role ?? ''));
            $user->assignRole($legacyRole === 'admin' ? $adminRole : $userRole);
        }
    }
}
