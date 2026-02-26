# Sistema de Permisos, Roles y Policies — Guía para traspasar a otro proyecto

> Documentación completa sobre cómo replicar el sistema de permisos basado en **Spatie Laravel Permission** + **Policies** en un nuevo proyecto Laravel.

---

## Tabla de contenidos

1. [Resumen del sistema](#resumen-del-sistema)
2. [Dependencias necesarias](#dependencias-necesarias)
3. [Tablas y migraciones](#tablas-y-migraciones)
4. [Configuración (`config/permission.php` y `config/auth.php`)](#configuración)
5. [Modelo User — trait HasRoles](#modelo-user--trait-hasroles)
6. [Roles y permisos definidos](#roles-y-permisos-definidos)
7. [Seeders — orden de ejecución y contenido](#seeders--orden-de-ejecución-y-contenido)
8. [Policies — autorización por recurso](#policies--autorización-por-recurso)
9. [Registro de policies en el ServiceProvider](#registro-de-policies-en-el-serviceprovider)
10. [Controladores — cómo usan la autorización](#controladores--cómo-usan-la-autorización)
11. [Middleware de autenticación](#middleware-de-autenticación)
12. [Rutas y estructura modular](#rutas-y-estructura-modular)
13. [Pasos para traspasar a otro proyecto](#pasos-para-traspasar-a-otro-proyecto)
14. [Checklist final](#checklist-final)

---

## Resumen del sistema

El proyecto usa **3 roles** (`admin_tenant`, `worker`, `client`) con **9 permisos** distribuidos en 3 recursos (`users`, `vehicles`, `incidences`). Cada recurso tiene su propia **Policy** que verifica los permisos del usuario autenticado.

**Flujo resumido:**

```
Request HTTP
  → Middleware: identifica tenant + autentica usuario
    → Controlador: llama $this->authorize('acción', Modelo)
      → Policy: verifica $user->hasPermissionTo('recurso.acción.all')
        → ✅ Permitir / ❌ 403 Forbidden
```

**Paquete usado:** `spatie/laravel-permission` v6+

---

## Dependencias necesarias

### Instalar Spatie Permission

```bash
composer require spatie/laravel-permission
```

### Publicar configuración

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Esto genera:
- `config/permission.php` — configuración del paquete
- Una migración para las tablas de permisos (que debes mover a `database/migrations/tenant/` si usas multi-tenancy)

---

## Tablas y migraciones

### Tablas que crea Spatie

La migración `create_permission_tables` crea **5 tablas**:

| Tabla | Descripción |
|-------|-------------|
| `permissions` | Lista de permisos (`id`, `name`, `guard_name`) |
| `roles` | Lista de roles (`id`, `name`, `guard_name`) |
| `model_has_permissions` | Relación directa usuario ↔ permiso |
| `model_has_roles` | Relación usuario ↔ rol |
| `role_has_permissions` | Relación rol ↔ permiso |

### Diagrama ER

```
┌──────────────┐     ┌─────────────────────┐     ┌──────────────┐
│  permissions │     │ role_has_permissions │     │    roles     │
├──────────────┤     ├─────────────────────┤     ├──────────────┤
│ id (PK)      │◄────│ permission_id (FK)  │     │ id (PK)      │
│ name         │     │ role_id (FK)        │────►│ name         │
│ guard_name   │     └─────────────────────┘     │ guard_name   │
│ created_at   │                                  │ created_at   │
│ updated_at   │                                  │ updated_at   │
└──────────────┘                                  └──────────────┘
       │                                                 │
       │  ┌──────────────────────────┐                   │
       │  │ model_has_permissions    │                   │
       │  ├──────────────────────────┤                   │
       └──│ permission_id (FK)       │                   │
          │ model_type               │                   │
          │ model_id                 │                   │
          └──────────────────────────┘                   │
                                                         │
          ┌──────────────────────────┐                   │
          │ model_has_roles          │                   │
          ├──────────────────────────┤                   │
          │ role_id (FK)             │───────────────────┘
          │ model_type               │
          │ model_id                 │
          └──────────────────────────┘
```

### Migración completa

Archivo: `database/migrations/tenant/2026_01_31_124700_create_permission_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        // Tabla: permissions
        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        // Tabla: roles
        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        // Tabla: model_has_permissions
        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        // Tabla: model_has_roles
        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole) {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivotRole)->references('id')->on($tableNames['roles'])->onDelete('cascade');
            $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        // Tabla: role_has_permissions
        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);
            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $table->foreign($pivotRole)->references('id')->on($tableNames['roles'])->onDelete('cascade');
            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')->store(
            config('permission.cache.store') != 'default' ? config('permission.cache.store') : null
        )->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
```

### Tabla `users` (referencia)

La tabla `users` necesita existir antes de asignar roles. No requiere columna `role` — Spatie lo gestiona todo mediante la tabla pivote `model_has_roles`.

```php
Schema::create('users', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('name', 255);
    $table->string('email', 255)->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password', 255);
    $table->string('phone', 20)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_login_at')->nullable();
    $table->string('remember_token', 100)->nullable();
    $table->timestamps();
    $table->timestamp('deleted_at')->nullable();
});
```

> **Importante**: NO uses una columna `role` enum en la tabla users. Los roles se gestionan exclusivamente a través de las tablas de Spatie (`model_has_roles`). Si tu tabla users ya tiene una columna `role`, créala con una migración adicional que la elimine.

---

## Configuración

### `config/permission.php`

Valores clave que necesitas:

```php
return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,        // default 'role_id'
        'permission_pivot_key' => null,  // default 'permission_id'
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    'teams' => false,  // No usamos teams

    'register_permission_check_method' => true,
    'register_octane_reset_listener' => false,
    'events_enabled' => false,
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
```

### `config/auth.php`

Necesitas definir el **guard `tenant`** para que Spatie sepa bajo qué guard operar:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'tenant' => [          // ← Guard para usuarios del tenant
        'driver' => 'session',
        'provider' => 'users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Modules\User\Models\User::class,  // ← Tu modelo User
    ],
],
```

> **¿Por qué un guard separado?** Para que los permisos del guard `tenant` estén completamente separados de cualquier otro guard (como `central` para super admins). Si tu proyecto no tiene multi-tenancy, puedes usar directamente el guard `web`.

---

## Modelo User — trait HasRoles

El modelo `User` debe usar el trait `HasRoles` de Spatie y definir el `guard_name`:

```php
<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;  // ← Importar

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;  // ← Usar HasRoles

    /**
     * El guard que usa este modelo para Spatie Permission.
     * TODOS los permisos y roles se buscarán con este guard_name.
     */
    protected $guard_name = 'tenant';  // ← Debe coincidir con el guard de config/auth.php

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
```

### ¿Qué te da el trait `HasRoles`?

El trait añade estos métodos al modelo User:

| Método | Descripción | Ejemplo |
|--------|-------------|---------|
| `$user->assignRole('worker')` | Asigna un rol al usuario | Crear usuario |
| `$user->removeRole('worker')` | Quita un rol | Cambiar rol |
| `$user->syncRoles(['admin_tenant'])` | Sincroniza roles (quita los demás) | Actualizar usuario |
| `$user->hasRole('admin_tenant')` | ¿Tiene este rol? | Condicionales |
| `$user->hasPermissionTo('vehicles.view.all')` | ¿Tiene este permiso (directo o vía rol)? | Policies |
| `$user->getRoleNames()` | Collection con nombres de roles | API response |
| `$user->getAllPermissions()` | Collection de todos los permisos | Debug |

---

## Roles y permisos definidos

### Roles

| Rol | Guard | Descripción |
|-----|-------|-------------|
| `admin_tenant` | `tenant` | Administrador del tenant — acceso total a todos los recursos |
| `worker` | `tenant` | Trabajador — acceso operativo (ver y gestionar, no eliminar) |
| `client` | `tenant` | Cliente final — acceso limitado (ver y gestionar su propio perfil) |

### Permisos por recurso

#### Recurso: Users (`users.*`)

| Permiso | Descripción | admin_tenant | worker | client |
|---------|-------------|:---:|:---:|:---:|
| `users.view.all` | Ver listado de usuarios | ✅ | ✅ | ❌ |
| `users.manage.all` | Crear y editar usuarios | ✅ | ❌ | ❌ |
| `users.destroy.all` | Eliminar usuarios | ✅ | ❌ | ❌ |

#### Recurso: Vehicles (`vehicles.*`)

| Permiso | Descripción | admin_tenant | worker | client |
|---------|-------------|:---:|:---:|:---:|
| `vehicles.view.all` | Ver listado de vehículos | ✅ | ✅ | ✅ |
| `vehicles.manage.all` | Crear y editar vehículos | ✅ | ✅ | ❌ |
| `vehicles.destroy.all` | Eliminar vehículos | ✅ | ❌ | ❌ |

#### Recurso: Incidences (`incidences.*`)

| Permiso | Descripción | admin_tenant | worker | client |
|---------|-------------|:---:|:---:|:---:|
| `incidences.view.all` | Ver todas las incidencias | ✅ | ✅ | ❌ |
| `incidences.manage.all` | Gestionar cualquier incidencia | ✅ | ✅ | ❌ |
| `incidences.destroy.all` | Eliminar incidencias | ✅ | ❌ | ❌ |

### Convención de nombres

Los permisos siguen el patrón: **`recurso.acción.alcance`**

```
vehicles.view.all
   │       │    │
   │       │    └── alcance: all = todos los registros
   │       └── acción: view, manage, destroy
   └── recurso: users, vehicles, incidences
```

### Cómo agregar un nuevo recurso

Si quieres agregar por ejemplo `bookings`, crea estos permisos:

```
bookings.view.all
bookings.manage.all
bookings.destroy.all
```

---

## Seeders — orden de ejecución y contenido

Los seeders se ejecutan en un **orden específico** porque hay dependencias entre ellos.

### Orden de ejecución

```
DatabaseSeeder
  ├── 1. RolePermissionSeeder    → Crea los 3 roles base
  ├── 2. UserSeeder              → Crea permisos de users y los asigna a roles
  ├── 3. VehiclePermissionSeeder → Crea permisos de vehicles y los asigna a roles
  ├── 4. Crear usuarios          → Crea usuarios y les asigna roles
  └── 5. IncidenceSeeder         → Crea permisos de incidences + datos de prueba
```

> **Importante**: Los roles deben existir ANTES de crear permisos (para poder asignarlos). Y los permisos/roles deben existir ANTES de crear usuarios (para poder asignarlos).

### Seeder 1: `RolePermissionSeeder` — Crea roles base

**Ubicación**: `app/modules/Auth/Seeders/RolePermissionSeeder.php`

```php
<?php

namespace App\Modules\Auth\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Crear los 3 roles con guard 'tenant'
        Role::firstOrCreate(
            ['name' => 'admin_tenant', 'guard_name' => 'tenant'],
            ['guard_name' => 'tenant']
        );

        Role::firstOrCreate(
            ['name' => 'worker', 'guard_name' => 'tenant'],
            ['guard_name' => 'tenant']
        );

        Role::firstOrCreate(
            ['name' => 'client', 'guard_name' => 'tenant'],
            ['guard_name' => 'tenant']
        );
    }
}
```

**Puntos clave:**
- Usa `firstOrCreate` para ser idempotente (no falla si ya existen)
- El `guard_name` debe ser `'tenant'` (o el guard que uses)

### Seeder 2: `UserSeeder` — Permisos de Users

**Ubicación**: `app/modules/User/Seeders/UserSeeder.php`

```php
<?php

namespace App\Modules\User\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Crear permisos
        $view = Permission::firstOrCreate(
            ['name' => 'users.view.all'],
            ['guard_name' => 'tenant']
        );

        $manage = Permission::firstOrCreate(
            ['name' => 'users.manage.all'],
            ['guard_name' => 'tenant']
        );

        $destroy = Permission::firstOrCreate(
            ['name' => 'users.destroy.all'],
            ['guard_name' => 'tenant']
        );

        // Asignar permisos a roles
        $adminRole = Role::where('name', 'admin_tenant')->where('guard_name', 'tenant')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo([$view, $manage, $destroy]);
        }

        $workerRole = Role::where('name', 'worker')->where('guard_name', 'tenant')->first();
        if ($workerRole) {
            $workerRole->givePermissionTo([$view]);
        }
        // client no tiene permisos de users
    }
}
```

### Seeder 3: `VehiclePermissionSeeder` — Permisos de Vehicles

**Ubicación**: `app/modules/Vehicle/Seeders/VehiclePermissionSeeder.php`

```php
<?php

namespace App\Modules\Vehicle\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VehiclePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $view = Permission::firstOrCreate(
            ['name' => 'vehicles.view.all', 'guard_name' => 'tenant']
        );

        $manage = Permission::firstOrCreate(
            ['name' => 'vehicles.manage.all', 'guard_name' => 'tenant']
        );

        $destroy = Permission::firstOrCreate(
            ['name' => 'vehicles.destroy.all', 'guard_name' => 'tenant']
        );

        // admin_tenant → ver + gestionar + eliminar
        $adminRole = Role::where('name', 'admin_tenant')->where('guard_name', 'tenant')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo([$view, $manage, $destroy]);
        }

        // worker → ver + gestionar
        $workerRole = Role::where('name', 'worker')->where('guard_name', 'tenant')->first();
        if ($workerRole) {
            $workerRole->givePermissionTo([$view, $manage]);
        }

        // client → solo ver
        $clientRole = Role::where('name', 'client')->where('guard_name', 'tenant')->first();
        if ($clientRole) {
            $clientRole->givePermissionTo([$view]);
        }
    }
}
```

### Seeder 4: `IncidenceSeeder` — Permisos de Incidences + datos de prueba

**Ubicación**: `app/modules/Incidences/Seeders/IncidenceSeeder.php`

```php
<?php

namespace App\Modules\Incidences\Seeders;

use App\Modules\Incidences\Models\Incidence;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IncidenceSeeder extends Seeder
{
    public function run(): void
    {
        $view = Permission::firstOrCreate(
            ['name' => 'incidences.view.all'],
            ['guard_name' => 'tenant']
        );

        $manage = Permission::firstOrCreate(
            ['name' => 'incidences.manage.all'],
            ['guard_name' => 'tenant']
        );

        $destroy = Permission::firstOrCreate(
            ['name' => 'incidences.destroy.all'],
            ['guard_name' => 'tenant']
        );

        // admin_tenant → ver + gestionar + eliminar
        $adminRole = Role::where('name', 'admin_tenant')->where('guard_name', 'tenant')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo([$view, $manage, $destroy]);
        }

        // worker → ver + gestionar
        $workerRole = Role::where('name', 'worker')->where('guard_name', 'tenant')->first();
        if ($workerRole) {
            $workerRole->givePermissionTo([$view, $manage]);
        }

        // client → no tiene permisos especiales (pero puede crear incidencias — ver Policy)

        // Datos de prueba
        Incidence::factory()->count(10)->create();
    }
}
```

### Patrón reutilizable para nuevos seeders

Si añades un recurso nuevo (ej: `bookings`), crea un seeder siguiendo este patrón:

```php
<?php

namespace App\Modules\Booking\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BookingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear permisos con guard_name explícito
        $view = Permission::firstOrCreate(
            ['name' => 'bookings.view.all', 'guard_name' => 'tenant']
        );
        $manage = Permission::firstOrCreate(
            ['name' => 'bookings.manage.all', 'guard_name' => 'tenant']
        );
        $destroy = Permission::firstOrCreate(
            ['name' => 'bookings.destroy.all', 'guard_name' => 'tenant']
        );

        // 2. Asignar a roles existentes
        $admin = Role::where('name', 'admin_tenant')->where('guard_name', 'tenant')->first();
        $admin?->givePermissionTo([$view, $manage, $destroy]);

        $worker = Role::where('name', 'worker')->where('guard_name', 'tenant')->first();
        $worker?->givePermissionTo([$view, $manage]);

        $client = Role::where('name', 'client')->where('guard_name', 'tenant')->first();
        $client?->givePermissionTo([$view]);
    }
}
```

### Crear usuarios con roles

```php
use App\Modules\User\Models\User;

// Crear usuario y asignar rol
$user = User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
]);

$user->assignRole('admin_tenant');  // ← Asigna el rol
```

### Registro de usuario (auto-asignación de rol `client`)

En el `AuthController`, cuando un usuario se registra, se le asigna automáticamente el rol `client`:

```php
public function register(Request $request)
{
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
    ]);

    $user->assignRole('client');  // ← Todo usuario que se registra es client

    return response()->json([
        'message' => 'User created successfully!',
        'user' => $user,
    ], 201);
}
```

---

## Policies — autorización por recurso

Las Policies encapsulan la lógica de autorización para cada modelo. Definen **qué usuario puede hacer qué acción** sobre qué recurso.

### `UserPolicy`

**Ubicación**: `app/modules/User/Policies/UserPolicy.php`

```php
<?php

namespace App\Modules\User\Policies;

use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * ¿Puede ver el listado de usuarios?
     * Solo admin y worker (tienen users.view.all)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view.all');
    }

    /**
     * ¿Puede ver un usuario específico?
     * Admin ve todos; el usuario ve solo su propio perfil
     */
    public function view(User $user, User $targetUser): bool
    {
        if ($user->hasPermissionTo('users.view.all')) {
            return true;
        }
        return $user->id === $targetUser->id;
    }

    /**
     * ¿Puede crear usuarios?
     * Solo admin (tiene users.manage.all)
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.manage.all');
    }

    /**
     * ¿Puede editar un usuario?
     * Admin puede editar cualquiera; usuario solo su propio perfil
     */
    public function update(User $user, User $targetUser): Response
    {
        if ($user->hasPermissionTo('users.manage.all')) {
            return Response::allow();
        }
        if ($user->id === $targetUser->id) {
            return Response::allow();
        }
        return Response::deny('You cannot update this user.');
    }

    /**
     * ¿Puede eliminar un usuario?
     * Admin puede eliminar otros; usuario puede eliminarse a sí mismo
     */
    public function delete(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return true;
        }
        if ($user->hasPermissionTo('users.destroy.all')) {
            return true;
        }
        return false;
    }
}
```

### `VehiclePolicy`

**Ubicación**: `app/modules/Vehicle/Policies/VehiclePolicy.php`

```php
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
```

### `IncidencePolicy`

**Ubicación**: `app/modules/Incidences/Policies/IncidencePolicy.php`

```php
<?php

namespace App\Modules\Incidences\Policies;

use App\Modules\Incidences\Models\Incidence;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;

class IncidencePolicy
{
    /**
     * Cualquier usuario autenticado puede ver el listado
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admin/worker ven todas; usuario solo las que reportó
     */
    public function view(User $user, Incidence $incidence): bool
    {
        if ($user->hasPermissionTo('incidences.view.all')) {
            return true;
        }
        return $user->id === $incidence->reported_by;
    }

    /**
     * Cualquier usuario autenticado puede crear incidencias
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Admin/worker actualizan cualquiera; usuario solo las suyas
     */
    public function update(User $user, Incidence $incidence): Response
    {
        if ($user->hasPermissionTo('incidences.manage.all')) {
            return Response::allow();
        }
        if ($user->id === $incidence->reported_by) {
            return Response::allow();
        }
        return Response::deny('You cannot update this incidence.');
    }

    /**
     * Solo admin puede eliminar (requiere incidences.destroy.all)
     */
    public function delete(User $user, Incidence $incidence): bool
    {
        return $user->hasPermissionTo('incidences.destroy.all');
    }

    /**
     * Solo admin/worker pueden resolver (requiere incidences.manage.all)
     */
    public function resolve(User $user, Incidence $incidence): bool
    {
        return $user->hasPermissionTo('incidences.manage.all');
    }
}
```

### Patrones de autorización usados en las Policies

| Patrón | Ejemplo | Cuándo usarlo |
|--------|---------|---------------|
| **Solo permiso** | `$user->hasPermissionTo('vehicles.view.all')` | Cuando la acción depende solo del rol |
| **Permiso O propietario** | `$user->hasPermissionTo(...) \|\| $user->id === $model->user_id` | Cuando el usuario puede actuar sobre sus propios recursos |
| **Siempre permitir** | `return true;` | Cuando cualquier usuario autenticado puede hacerlo |
| **Response::allow/deny** | `return Response::deny('Mensaje')` | Cuando quieres devolver un mensaje de error personalizado |

### Crear una Policy para un recurso nuevo

```bash
php artisan make:policy BookingPolicy --model=Booking
```

Plantilla base:

```php
<?php

namespace App\Modules\Booking\Policies;

use App\Modules\Booking\Models\Booking;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bookings.view.all');
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->hasPermissionTo('bookings.view.all')) {
            return true;
        }
        return $user->id === $booking->user_id;  // Ver solo los propios
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bookings.manage.all');
    }

    public function update(User $user, Booking $booking): Response
    {
        if ($user->hasPermissionTo('bookings.manage.all')) {
            return Response::allow();
        }
        return Response::deny('You cannot update this booking.');
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->hasPermissionTo('bookings.destroy.all');
    }
}
```

---

## Registro de policies en el ServiceProvider

Las policies se registran en `AppServiceProvider` (método `boot()`):

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyInitialized;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Resetear caché de permisos al cambiar de tenant
        // (necesario SOLO si usas multi-tenancy)
        Event::listen(TenancyInitialized::class, function () {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        });

        // Registrar policies
        Gate::policy(\App\Modules\Vehicle\Models\Vehicle::class, \App\Modules\Vehicle\Policies\VehiclePolicy::class);
        Gate::policy(\App\Modules\User\Models\User::class, \App\Modules\User\Policies\UserPolicy::class);
        Gate::policy(\App\Modules\Incidences\Models\Incidence::class, \App\Modules\Incidences\Policies\IncidencePolicy::class);
    }
}
```

> **Alternativa**: Laravel soporta auto-discovery de policies si se nombran por convención (`app/Policies/VehiclePolicy.php` para el modelo `Vehicle`). En estructura modular es más claro registrarlas manualmente con `Gate::policy()`.

---

## Controladores — cómo usan la autorización

Los controladores usan el método `$this->authorize()` (heredado de `Controller`) que internamente llama a la Policy correspondiente.

### Patrón general

```php
class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        // Llama a VehiclePolicy::viewAny($user)
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::paginate(10);
        return response()->json(['data' => $vehicles]);
    }

    public function show($id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        // Llama a VehiclePolicy::view($user, $vehicle)
        $this->authorize('view', $vehicle);

        return response()->json(['data' => $vehicle]);
    }

    public function store(CreateVehicleRequest $request): JsonResponse
    {
        // Llama a VehiclePolicy::create($user)
        $this->authorize('create', Vehicle::class);

        $vehicle = Vehicle::create($request->validated());
        return response()->json(['data' => $vehicle], 201);
    }

    public function update(UpdateVehicleRequest $request, $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        // Llama a VehiclePolicy::update($user, $vehicle)
        $this->authorize('update', $vehicle);

        $vehicle->update($request->validated());
        return response()->json(['data' => $vehicle]);
    }

    public function destroy($id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        // Llama a VehiclePolicy::delete($user, $vehicle)
        $this->authorize('delete', $vehicle);

        $vehicle->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
```

### Mapeo de `$this->authorize()` → Policy

| Llamada en Controller | Método de Policy ejecutado |
|-----------------------|---------------------------|
| `$this->authorize('viewAny', Vehicle::class)` | `VehiclePolicy::viewAny($user)` |
| `$this->authorize('view', $vehicle)` | `VehiclePolicy::view($user, $vehicle)` |
| `$this->authorize('create', Vehicle::class)` | `VehiclePolicy::create($user)` |
| `$this->authorize('update', $vehicle)` | `VehiclePolicy::update($user, $vehicle)` |
| `$this->authorize('delete', $vehicle)` | `VehiclePolicy::delete($user, $vehicle)` |

Si la Policy retorna `false` o `Response::deny()`, Laravel automáticamente devuelve una respuesta **403 Forbidden**.

### Asignación de roles en el `UserController`

El `UserController` permite que un admin asigne roles al crear o actualizar usuarios:

```php
// Al crear usuario
public function store(CreateUserRequest $request): JsonResponse
{
    $this->authorize('create', User::class);
    $validated = $request->validated();

    $user = User::create([...]);
    $user->assignRole($validated['role']);  // ← Asigna rol del request

    return response()->json(['data' => $user], 201);
}

// Al actualizar usuario — solo admin puede cambiar roles
public function update(UpdateUserRequest $request, $userId): JsonResponse
{
    // ...
    if (isset($validated['role']) && (
        auth()->user()->hasRole('admin_tenant') || auth()->user()->hasRole('tenant_admin')
    )) {
        $user->syncRoles($validated['role']);  // ← Reemplaza roles
    }
}
```

---

## Middleware de autenticación

### `AuthenticateTenant`

**Ubicación**: `app/Http/Middleware/AuthenticateTenant.php`

Este middleware autentica el Bearer token de Sanctum y carga roles/permisos:

```php
<?php

namespace App\Http\Middleware;

use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticateTenant
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Autenticar y cargar el usuario
        $user = $this->authenticateToken($token, $tenantId);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Setear usuario autenticado en ambos guards
        auth('tenant')->setUser($user);
        auth()->setUser($user);

        return $next($request);
    }

    private function authenticateToken(string $token, string $tenantId): ?User
    {
        [$tokenId, $tokenSecret] = explode('|', $token, 2);
        $hashedToken = hash('sha256', $tokenSecret);

        // Buscar token válido
        $personalAccessToken = DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->where('token', $hashedToken)
            ->first();

        if (! $personalAccessToken) {
            return null;
        }

        $user = User::find($personalAccessToken->tokenable_id);
        if (! $user) {
            return null;
        }

        // ⚠️ IMPORTANTE: Cargar roles y permisos del usuario
        $user->load('roles', 'permissions');

        return $user;
    }
}
```

> **Línea clave**: `$user->load('roles', 'permissions')` — sin esto, los `hasPermissionTo()` de las Policies no funcionarán.

---

## Rutas y estructura modular

### Archivo principal de rutas: `routes/api.php`

```php
<?php

use App\Http\Middleware\AuthenticateTenant;
use App\Http\Middleware\InitializeTenantFromHeader;
use Illuminate\Support\Facades\Route;

// Rutas de tenant (requieren X-Tenant-ID)
Route::prefix('tenant')
    ->middleware(['api', InitializeTenantFromHeader::class])
    ->group(function () {
        // Rutas públicas del tenant (login, register)
        require base_path('app/modules/Auth/Routes/api.php');

        // Rutas protegidas (requieren Bearer token)
        Route::middleware(AuthenticateTenant::class)->group(function () {
            require base_path('app/modules/User/Routes/api.php');
            require base_path('app/modules/Vehicle/Routes/api.php');
            require base_path('app/modules/Incidences/Routes/api.php');
        });
    });
```

### Rutas por módulo

**Auth** (`app/modules/Auth/Routes/api.php`):
```php
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware(AuthenticateTenant::class)->group(function () {
    Route::get('user', [AuthController::class, 'me']);
    Route::get('profile', [AuthController::class, 'me']);
    Route::patch('profile', [AuthController::class, 'updateProfile']);
    Route::get('roles', [AuthController::class, 'roles']);
    Route::post('logout', [AuthController::class, 'logout']);
});
```

**Users** (`app/modules/User/Routes/api.php`):
```php
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{userId}', [UserController::class, 'show']);
Route::put('/users/{userId}', [UserController::class, 'update']);
Route::delete('/users/{userId}', [UserController::class, 'destroy']);
```

**Vehicles** (`app/modules/Vehicle/Routes/api.php`):
```php
Route::get('/vehicles', [VehicleController::class, 'index']);
Route::post('/vehicles', [VehicleController::class, 'store']);
Route::get('/vehicles/{vehicleId}', [VehicleController::class, 'show']);
Route::match(['put', 'patch'], '/vehicles/{vehicleId}', [VehicleController::class, 'update']);
Route::delete('/vehicles/{vehicleId}', [VehicleController::class, 'destroy']);
```

**Incidences** (`app/modules/Incidences/Routes/api.php`):
```php
Route::get('/incidences', [IncidenceController::class, 'index']);
Route::post('/incidences', [IncidenceController::class, 'store']);
Route::get('/incidences/{id}', [IncidenceController::class, 'show']);
Route::put('/incidences/{id}', [IncidenceController::class, 'update']);
Route::delete('/incidences/{id}', [IncidenceController::class, 'destroy']);
Route::get('/incidences-trashed', [IncidenceController::class, 'trashed']);
```

---

## Pasos para traspasar a otro proyecto

### Paso 1: Instalar Spatie Permission

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

### Paso 2: Configurar `config/permission.php`

Copia el archivo de configuración. Si no usas multi-tenancy, los valores por defecto están bien. Si lo usas, asegúrate de que el `guard_name` sea consistente.

### Paso 3: Configurar guard en `config/auth.php`

Añade el guard `tenant` (o el nombre que prefieras):

```php
'guards' => [
    'web' => [...],
    'tenant' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

### Paso 4: Migración de tablas de permisos

Copia la migración `create_permission_tables` a tu directorio de migraciones:

```bash
# Si NO usas multi-tenancy:
php artisan migrate

# Si usas multi-tenancy con schemas:
# Mueve la migración a database/migrations/tenant/ y ejecuta:
php artisan tenants:migrate
```

### Paso 5: Añadir `HasRoles` al modelo User

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    protected $guard_name = 'tenant'; // o 'web' si no usas guard custom
}
```

### Paso 6: Crear los seeders

Copia los seeders en este orden:

1. **Roles** → `RolePermissionSeeder` (crea los 3 roles)
2. **Permisos por recurso** → Un seeder por módulo (`UserSeeder`, `VehiclePermissionSeeder`, etc.)
3. **Usuarios de prueba** → Con `$user->assignRole('admin_tenant')`

### Paso 7: Crear las Policies

Para cada recurso, crea una Policy:

```bash
php artisan make:policy NombrePolicy --model=NombreModelo
```

Implementa los métodos `viewAny`, `view`, `create`, `update`, `delete` usando `$user->hasPermissionTo('...')`.

### Paso 8: Registrar las Policies

En `AppServiceProvider::boot()`:

```php
Gate::policy(Vehicle::class, VehiclePolicy::class);
Gate::policy(User::class, UserPolicy::class);
// ...
```

### Paso 9: Usar `$this->authorize()` en los controladores

En cada método del controlador, añade la llamada de autorización:

```php
$this->authorize('viewAny', Vehicle::class);  // Para index
$this->authorize('view', $vehicle);            // Para show
$this->authorize('create', Vehicle::class);    // Para store
$this->authorize('update', $vehicle);          // Para update
$this->authorize('delete', $vehicle);          // Para destroy
```

### Paso 10: Limpiar caché de permisos (multi-tenancy)

Si usas multi-tenancy, añade el listener en `AppServiceProvider`:

```php
Event::listen(TenancyInitialized::class, function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});
```

---

## Checklist final

### Archivos necesarios

| Archivo | Propósito |
|---------|-----------|
| `config/permission.php` | Configuración de Spatie |
| `config/auth.php` | Guards y providers |
| `migration: create_permission_tables` | Tablas de roles/permisos |
| `migration: create_users_table` | Tabla de usuarios (sin columna `role`) |
| `Model: User.php` | Con trait `HasRoles` y `$guard_name` |
| `Seeder: RolePermissionSeeder` | Crea los 3 roles base |
| `Seeder: UserSeeder` | Permisos de users → roles |
| `Seeder: VehiclePermissionSeeder` | Permisos de vehicles → roles |
| `Seeder: IncidenceSeeder` | Permisos de incidences → roles |
| `Policy: UserPolicy` | Autorización para users |
| `Policy: VehiclePolicy` | Autorización para vehicles |
| `Policy: IncidencePolicy` | Autorización para incidences |
| `Controller: UserController` | CRUD con `$this->authorize()` |
| `Controller: VehicleController` | CRUD con `$this->authorize()` |
| `Controller: IncidenceController` | CRUD con `$this->authorize()` |
| `Middleware: AuthenticateTenant` | Autentica token y carga roles |
| `AppServiceProvider` | Registra policies + reset caché permisos |

### Verificación rápida

```bash
# 1. Ejecutar migración
php artisan migrate

# 2. Ejecutar seeders
php artisan db:seed

# 3. Verificar roles creados
php artisan tinker
>>> \Spatie\Permission\Models\Role::all()->pluck('name');
# → ["admin_tenant", "worker", "client"]

# 4. Verificar permisos creados
>>> \Spatie\Permission\Models\Permission::all()->pluck('name');
# → ["users.view.all", "users.manage.all", "users.destroy.all", "vehicles.view.all", ...]

# 5. Verificar asignación
>>> $admin = \App\Modules\User\Models\User::where('email', 'admin@1.com')->first();
>>> $admin->getAllPermissions()->pluck('name');
# → Los 9 permisos

>>> $worker = \App\Modules\User\Models\User::where('email', 'worker@1.com')->first();
>>> $worker->getAllPermissions()->pluck('name');
# → ["users.view.all", "vehicles.view.all", "vehicles.manage.all", "incidences.view.all", "incidences.manage.all"]

>>> $client = \App\Modules\User\Models\User::where('email', 'client@1.com')->first();
>>> $client->getAllPermissions()->pluck('name');
# → ["vehicles.view.all"]
```

### Matriz completa de acceso

| Acción | Ruta API | Policy Method | admin_tenant | worker | client |
|--------|----------|---------------|:---:|:---:|:---:|
| Listar users | `GET /users` | `viewAny` | ✅ | ✅ | ❌ |
| Ver user | `GET /users/{id}` | `view` | ✅ todos | ✅ todos | solo propio |
| Crear user | `POST /users` | `create` | ✅ | ❌ | ❌ |
| Editar user | `PUT /users/{id}` | `update` | ✅ todos | ❌ (solo propio) | solo propio |
| Eliminar user | `DELETE /users/{id}` | `delete` | ✅ | ❌ | solo propio |
| Listar vehicles | `GET /vehicles` | `viewAny` | ✅ | ✅ | ✅ |
| Ver vehicle | `GET /vehicles/{id}` | `view` | ✅ | ✅ | ✅ |
| Crear vehicle | `POST /vehicles` | `create` | ✅ | ✅ | ❌ |
| Editar vehicle | `PUT /vehicles/{id}` | `update` | ✅ | ✅ | ❌ |
| Eliminar vehicle | `DELETE /vehicles/{id}` | `delete` | ✅ | ❌ | ❌ |
| Listar incidences | `GET /incidences` | `viewAny` | ✅ | ✅ | ✅ |
| Ver incidence | `GET /incidences/{id}` | `view` | ✅ todas | ✅ todas | solo propias |
| Crear incidence | `POST /incidences` | `create` | ✅ | ✅ | ✅ |
| Editar incidence | `PUT /incidences/{id}` | `update` | ✅ todas | ✅ todas | solo propias |
| Eliminar incidence | `DELETE /incidences/{id}` | `delete` | ✅ | ❌ | ❌ |
| Resolver incidence | custom | `resolve` | ✅ | ✅ | ❌ |
