# PostgreSQL Multi-Tenancy — Guía completa

> Documentación técnica sobre cómo funciona la multi-tenancy con PostgreSQL schemas en este proyecto (Stancl/Tenancy + Laravel 12).

---

## Tabla de contenidos

1. [Arquitectura general](#arquitectura-general)
2. [Cómo funciona PostgreSQL con schemas](#cómo-funciona-postgresql-con-schemas)
3. [Setup desde cero](#setup-desde-cero)
4. [Configuración de la base de datos](#configuración-de-la-base-de-datos)
5. [Migraciones centrales vs tenant](#migraciones-centrales-vs-tenant)
6. [Ciclo de vida de un tenant](#ciclo-de-vida-de-un-tenant)
7. [Header X-Tenant-ID y middleware](#header-x-tenant-id-y-middleware)
8. [Autenticación por tenant](#autenticación-por-tenant)
9. [Seeders](#seeders)
10. [Roles, permisos y policies](#roles-permisos-y-policies)
11. [Comandos útiles](#comandos-útiles)
12. [Troubleshooting](#troubleshooting)

---

## Arquitectura general

El proyecto usa **Stancl/Tenancy v3** con el enfoque de **un schema de PostgreSQL por tenant**. Esto significa que:

- Existe **un único servidor PostgreSQL** y **una única base de datos** (`sims`).
- Las tablas globales/compartidas viven en el schema `public`.
- Cada tenant tiene su **propio schema** con el formato `tenant_{id}` (ej: `tenant_1`, `tenant_2`).
- Los schemas de tenant son **completamente aislados** entre sí: cada uno tiene sus propias tablas de `users`, `vehicles`, `incidences`, etc.

```
Base de datos: sims
├── public (schema central)
│   ├── plans
│   ├── features
│   ├── plan_features
│   ├── tenants
│   ├── domains
│   ├── global_admins
│   ├── tenant_invoices
│   ├── cache
│   ├── jobs / job_batches / failed_jobs
│   └── permissions / roles (central)
│
├── tenant_1 (schema del Tenant 1 - Acme Corp)
│   ├── users
│   ├── vehicles
│   ├── incidences
│   ├── bookings
│   ├── payments
│   ├── geofences
│   ├── maintenance
│   ├── support_tickets
│   ├── api_keys
│   ├── tenant_branding
│   ├── vehicle_locations
│   ├── audit_logs
│   ├── payment_webhook_events
│   ├── personal_access_tokens
│   └── permissions / roles / model_has_roles ...
│
├── tenant_2 (schema del Tenant 2 - Pollos Hermanos)
│   └── (mismas tablas que tenant_1, datos independientes)
│
└── tenant_N ...
```

---

## Cómo funciona PostgreSQL con schemas

### ¿Qué es un schema en PostgreSQL?

Un **schema** es un namespace dentro de una base de datos PostgreSQL. Es como una "carpeta" que agrupa tablas. Por defecto toda base de datos tiene el schema `public`.

### `search_path`

PostgreSQL usa la variable `search_path` para decidir en qué schema buscar las tablas. Cuando el middleware de tenancy se activa, ejecuta:

```sql
SET search_path TO "tenant_1", public;
```

Esto hace que cualquier query `SELECT * FROM users` vaya primero al schema `tenant_1`. Si la tabla no existe ahí, busca en `public`. Así las tablas compartidas (como `plans`) siguen accesibles.

### Ventajas de este enfoque

| Ventaja | Descripción |
|---------|-------------|
| **Aislamiento total** | Los datos de cada tenant están en schemas separados. No hay riesgo de filtración de datos. |
| **Sin columna `tenant_id`** | No necesitas agregar `WHERE tenant_id = X` a cada query. El aislamiento es por schema. |
| **Migraciones independientes** | Puedes migrar un tenant sin afectar a los demás. |
| **Backup granular** | Puedes hacer `pg_dump` de un solo schema si necesitas. |
| **Un solo servidor** | No necesitas múltiples bases de datos ni servidores. |

---

## Setup desde cero

### 1. Requisitos previos

- Docker y Docker Compose instalados
- PHP 8.4+ (si no usas Docker)
- PostgreSQL 15+ con PostGIS 3.3+

### 2. Clonar el proyecto y configurar `.env`

```bash
cp .env.example .env
```

Variables relevantes para PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres      # 'postgres' si usas Docker, '127.0.0.1' si es local
DB_PORT=5432
DB_DATABASE=sims
DB_USERNAME=sims_user
DB_PASSWORD=sims_password
```

### 3. Levantar contenedores con Docker

```bash
docker-compose up -d
```

Esto levanta:
- **`sims_postgres`** — PostgreSQL 15 con PostGIS en el puerto `5432`
- **`sims_pgadmin`** — pgAdmin para gestión visual en el puerto `5050`
- **`sims_api`** — Laravel API en el puerto `8000`

### 4. Instalar dependencias

```bash
# Dentro del contenedor
docker-compose exec api composer install

# O localmente
composer install
```

### 5. Generar application key

```bash
docker-compose exec api php artisan key:generate
```

### 6. Ejecutar migraciones centrales (schema `public`)

```bash
docker-compose exec api php artisan migrate
```

Esto crea en el schema `public`:
- Extensiones (`uuid-ossp`, `postgis`)
- `global_admins`
- `cache`, `jobs`, `job_batches`, `failed_jobs`
- `plans`, `features`, `plan_features`
- `tenants`, `domains`
- `tenant_invoices`
- `permissions`, `roles` (centrales)

### 7. Ejecutar seeders (crea tenants + sus schemas)

```bash
docker-compose exec api php artisan db:seed
```

Este comando ejecuta `DatabaseSeeder`, que internamente:

1. **`GlobalAdminSeeder`** → Crea el super admin (`admin@sims.local` / `password`)
2. **`PlanSeeder`** → Crea los planes (Starter, Professional, Enterprise)
3. **`TenantSeeder`** → Crea los tenants y sus schemas:

   Para **cada tenant**:
   - Crea el registro en `public.tenants`
   - Crea el dominio en `public.domains`
   - Crea el schema `tenant_{id}` en PostgreSQL
   - Ejecuta todas las migraciones de `database/migrations/tenant/`
   - Ejecuta los seeders de roles, permisos, usuarios y datos de prueba

### Resultado final

Después del paso 7 tendrás:

| Schema | Contenido |
|--------|-----------|
| `public` | Tablas centrales + 1 global admin + 3 planes |
| `tenant_1` | Acme Corp — usuarios, vehículos, incidencias de prueba |
| `tenant_2` | Pollos Hermanos — usuarios, vehículos, incidencias de prueba |

---

## Configuración de la base de datos

### `config/database.php`

La conexión principal es `pgsql`:

```php
'pgsql' => [
    'driver'      => 'pgsql',
    'host'        => env('DB_HOST', '127.0.0.1'),
    'port'        => env('DB_PORT', '5432'),
    'database'    => env('DB_DATABASE', 'laravel'),
    'username'    => env('DB_USERNAME', 'root'),
    'password'    => env('DB_PASSWORD', ''),
    'charset'     => 'utf8',
    'search_path' => 'public',     // ← por defecto apunta a public
    'sslmode'     => env('DB_SSLMODE', 'prefer'),
],
```

### `config/tenancy.php`

Configuración clave:

```php
'database' => [
    'central_connection' => env('DB_CONNECTION', 'pgsql'),
    'prefix' => 'tenant_',   // los schemas se llaman tenant_1, tenant_2, etc.
    'suffix' => '',
    'managers' => [
        'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
    ],
],
```

- **`prefix`**: El prefijo para el nombre del schema. Con `'tenant_'` y un tenant con `id = 1`, el schema será `tenant_1`.
- **`PostgreSQLSchemaManager`**: Manager que usa `CREATE SCHEMA` / `DROP SCHEMA` en vez de crear bases de datos separadas.

### Bootstrappers activos

```php
'bootstrappers' => [
    DatabaseTenancyBootstrapper::class,    // Cambia el search_path al schema del tenant
    CacheTenancyBootstrapper::class,       // Aísla la caché por tenant
    FilesystemTenancyBootstrapper::class,  // Aísla storage por tenant
    QueueTenancyBootstrapper::class,       // Los jobs recuerdan el tenant
],
```

---

## Migraciones centrales vs tenant

En multi-tenancy con schemas, las migraciones se **separan en dos carpetas** según a qué schema pertenecen.

### Migraciones centrales (`database/migrations/`)

Son las migraciones normales de Laravel. Se ejecutan con `php artisan migrate` y afectan **solo al schema `public`**. Aquí van todas las tablas compartidas entre tenants:

- Tablas del propio paquete Stancl: `tenants`, `domains`
- Tablas de infraestructura: `cache`, `jobs`, `failed_jobs`
- Tablas de negocio global: `plans`, `features`, `global_admins`, `tenant_invoices`
- Cualquier tabla que NO sea específica de un tenant

**Crear una migración central** (funciona igual que siempre):

```bash
php artisan make:migration create_plans_table
# → Se crea en database/migrations/
```

### Migraciones de tenant (`database/migrations/tenant/`)

Se ejecutan **dentro del schema de cada tenant** cuando se crea o migra un tenant. Aquí van las tablas de negocio que cada tenant tiene de forma aislada: `users`, `vehicles`, `bookings`, `incidences`, etc.

**Crear una migración de tenant** — se genera igual pero luego se **mueve manualmente** a la subcarpeta `tenant/`:

```bash
# 1. Generar la migración normalmente
php artisan make:migration create_products_table

# 2. Mover el archivo a la carpeta de tenant
mv database/migrations/2026_XX_XX_create_products_table.php database/migrations/tenant/
```

> **Importante**: Laravel no tiene un flag para generar directamente en `tenant/`. Siempre se genera en `database/migrations/` y hay que moverla.

### Diferencias clave

| Aspecto | Central (`migrations/`) | Tenant (`migrations/tenant/`) |
|---------|------------------------|-------------------------------|
| **Schema destino** | `public` | `tenant_{id}` |
| **Comando para ejecutar** | `php artisan migrate` | `php artisan tenants:migrate` |
| **Se ejecuta en** | Una sola vez | Una vez **por cada tenant** |
| **Cuándo se ejecuta auto** | Al hacer `migrate` | Al crear un tenant nuevo (pipeline) |
| **Ejemplo de tablas** | `tenants`, `plans`, `domains` | `users`, `vehicles`, `bookings` |

### ¿Cómo sabe Stancl qué migraciones son de tenant?

La configuración en `config/tenancy.php` indica la ruta explícitamente:

```php
'migration_parameters' => [
    '--force' => true,
    '--path'  => [database_path('migrations/tenant')],
    '--realpath' => true,
],
```

Stancl **solo ejecuta lo que esté en `database/migrations/tenant/`** cuando corre migraciones de tenant. Todo lo que esté fuera de esa carpeta se ignora.

### ¿Cómo se ejecutan automáticamente?

Cuando se crea un nuevo tenant con `Tenant::create([...])`, el `TenancyServiceProvider` dispara un pipeline de Jobs:

1. **`Jobs\CreateDatabase`** — Ejecuta `CREATE SCHEMA "tenant_{id}"` en PostgreSQL.
2. **`Jobs\MigrateDatabase`** — Corre `php artisan migrate` pero solo con los archivos de `migrations/tenant/`, dentro del schema recién creado.
3. **`Jobs\SeedDatabase`** — Ejecuta el seeder configurado (`TenantDatabaseSeeder`).

Este pipeline se define en `TenancyServiceProvider`:

```php
Events\TenantCreated::class => [
    JobPipeline::make([
        Jobs\CreateDatabase::class,
        Jobs\MigrateDatabase::class,
        Jobs\SeedDatabase::class,
    ])->send(function (Events\TenantCreated $event) {
        return $event->tenant;
    })->shouldBeQueued(false),   // false = síncrono, true = cola de jobs
],
```

### Migrar tenants manualmente

Si agregas una nueva migración de tenant a un proyecto que ya tiene tenants creados:

```bash
# Migrar TODOS los tenants existentes
php artisan tenants:migrate

# Migrar solo un tenant específico
php artisan tenants:migrate --tenants=1

# Rollback de todos los tenants
php artisan tenants:rollback

# Ver estado de migraciones de un tenant
php artisan tenants:migrate-status --tenants=1
```

### Ejemplo completo: agregar tabla `products` a todos los tenants

```bash
# 1. Crear la migración
php artisan make:migration create_products_table

# 2. Moverla a la carpeta de tenant
mv database/migrations/2026_*_create_products_table.php database/migrations/tenant/

# 3. Editar el archivo con la estructura deseada (Schema::create, etc.)

# 4. Aplicar a todos los tenants existentes
php artisan tenants:migrate
```

Cualquier tenant nuevo que se cree **después** de este paso también recibirá la tabla automáticamente, porque el pipeline ejecuta todas las migraciones de `tenant/`.

---

## Ciclo de vida de un tenant

### Creación automática (vía eventos en `TenancyServiceProvider`)

```
Tenant::create([...])
    ↓
TenantCreated Event
    ↓
Pipeline (síncrono):
    1. CreateDatabase    → CREATE SCHEMA "tenant_X"
    2. MigrateDatabase   → Ejecuta migraciones en database/migrations/tenant/
    3. SeedDatabase      → Ejecuta TenantDatabaseSeeder
```

### Eliminación automática

```
$tenant->delete()
    ↓
TenantDeleted Event
    ↓
Pipeline:
    1. DeleteDatabase    → DROP SCHEMA "tenant_X" CASCADE
```

### Inicialización de tenancy (en runtime)

Cuando llega una request con el header `X-Tenant-ID`:

```
Request HTTP → Middleware InitializeTenantFromHeader
    ↓
1. Lee header X-Tenant-ID
2. Busca el tenant en public.tenants
3. Ejecuta tenancy()->initialize($tenant)
4. SET search_path TO "tenant_X", public
    ↓
A partir de aquí, todos los queries van al schema del tenant
```

---

## Header X-Tenant-ID y middleware

### ¿Cómo se identifica el tenant?

El proyecto **no usa subdominios ni dominios** para identificar el tenant en la API. En su lugar, usa el **header HTTP `X-Tenant-ID`**.

### Middleware `InitializeTenantFromHeader`

Ubicación: `app/Http/Middleware/InitializeTenantFromHeader.php` (estructura estándar de Laravel)

Flujo:

1. Extrae el valor del header `X-Tenant-ID` de la request.
2. Si no existe → **400 Bad Request** (`Missing Tenant ID header`).
3. Valida que sea un entero positivo (`is_numeric`, `> 0`, solo dígitos).
4. Busca el tenant en la tabla `public.tenants` con `Tenant::findOrFail($id)`.
5. Inicializa la tenancy: `tenancy()->initialize($tenant)`.
6. Cambia el `search_path` de PostgreSQL: `SET search_path TO "tenant_X", public`.
7. Continúa con la request.

### Ejemplo de request

```bash
# Login en un tenant específico
curl -X POST http://localhost:8000/api/tenant/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 1" \
  -d '{"email": "admin@1.com", "password": "password"}'
```

```bash
# Listar vehículos del tenant 2 (requiere auth)
curl http://localhost:8000/api/tenant/vehicles \
  -H "X-Tenant-ID: 2" \
  -H "Authorization: Bearer {token}"
```

### Rutas y cómo se aplica el middleware

En `routes/api.php`:

```php
// Rutas centrales (NO necesitan X-Tenant-ID)
Route::prefix('central')->group(function () {
    // login/logout de global admin
});

// Rutas de tenant (NECESITAN X-Tenant-ID)
Route::prefix('tenant')
    ->middleware(['api', InitializeTenantFromHeader::class])
    ->group(function () {
        // Auth: register, login (público dentro del tenant)

        Route::middleware(AuthenticateTenant::class)->group(function () {
            // Rutas protegidas: users, vehicles, incidences, etc.
        });
    });
```

**Resumen de rutas:**

| Prefijo | Header requerido | Auth requerida | Rutas |
|---------|-----------------|----------------|-------|
| `api/central/` | Ninguno | `auth:central` (algunas) | `login`, `logout`, `me` |
| `api/tenant/` | `X-Tenant-ID` | No | `register`, `login` |
| `api/tenant/` | `X-Tenant-ID` | `AuthenticateTenant` | `users/*`, `vehicles/*`, `incidences/*`, `profile`, `logout` |

---

## Autenticación por tenant

### Middleware `AuthenticateTenant`

Ubicación: `app/Http/Middleware/AuthenticateTenant.php` (estructura estándar de Laravel)

Este middleware se ejecuta **después** de `InitializeTenantFromHeader` y verifica el Bearer token de Sanctum:

1. Extrae el Bearer token de la request.
2. Separa el token en `{id}|{secret}` y hashea el secret con SHA-256.
3. Busca el token en `tenant_X.personal_access_tokens` (directamente en el schema del tenant).
4. Busca el usuario asociado en `tenant_X.users`.
5. Carga roles y permisos del usuario.
6. Setea el usuario como autenticado en los guards `tenant` y `web`.

### Guards configurados (`config/auth.php`)

```php
'guards' => [
    'tenant' => [                          // Guard para usuarios de tenant
        'driver' => 'session',
        'provider' => 'users',             // → User model del módulo User
    ],
    'central' => [                         // Guard para global admins
        'driver' => 'session',
        'provider' => 'global_admins',     // → GlobalAdmin model
    ],
],
```

### Flujo completo de una request autenticada

```
Request: GET /api/tenant/vehicles
Headers: X-Tenant-ID: 1, Authorization: Bearer 5|abc123...

1. InitializeTenantFromHeader
   → Busca tenant_id=1 en public.tenants
   → SET search_path TO "tenant_1", public
   
2. AuthenticateTenant
   → Busca token en tenant_1.personal_access_tokens
   → Busca user en tenant_1.users
   → Carga roles/permisos del user
   → auth()->setUser($user)
   
3. VehicleController@index
   → Vehicle::all() → busca en tenant_1.vehicles
   → Policy check con permisos del usuario
```

---

## Seeders

### Orden de ejecución

El `DatabaseSeeder` orquesta todo. Los seeders se colocan en la ruta estándar de Laravel (`database/seeders/`):

```
DatabaseSeeder                              ← database/seeders/DatabaseSeeder.php
├── 1. GlobalAdminSeeder                    ← database/seeders/GlobalAdminSeeder.php
├── 2. PlanSeeder                           ← database/seeders/PlanSeeder.php
└── 3. TenantSeeder                         ← database/seeders/TenantSeeder.php
    └── Para cada tenant:
        ├── Crea registro en public.tenants
        ├── Crea dominio en public.domains
        ├── CREATE SCHEMA tenant_X
        ├── Ejecuta migraciones de tenant
        ├── RolePermissionSeeder            ← database/seeders/RolePermissionSeeder.php
        ├── PermissionSeeder                ← database/seeders/PermissionSeeder.php
        ├── Crea usuarios de prueba (admin, worker, client)
        └── Datos de prueba (faker)
```

Además, existe un seeder automático para cuando Stancl crea un tenant por su cuenta:

```
database/seeders/Tenant/TenantDatabaseSeeder.php   ← ejecutado por el pipeline de Stancl
└── RoleSeeder.php                                  ← crea roles base
```

### Seeders detallados

#### `GlobalAdminSeeder`
- Crea: `admin@sims.local` / `password`
- Tabla: `public.global_admins`

#### `PlanSeeder`
Planes creados en `public.plans`:

| Plan | Precio | Intervalo | Max Users | Max Vehicles |
|------|--------|-----------|-----------|--------------|
| Starter | 9.99 | monthly | 5 | 10 |
| Professional | 29.99 | monthly | 50 | 100 |
| Enterprise | 99.99 | yearly | ilimitado | ilimitado |

#### `TenantSeeder`
Tenants de prueba:

| ID | Nombre | Slug | Dominio | Plan |
|----|--------|------|---------|------|
| 1 | Acme Corp | acme-corp | acme.local | Starter |
| 2 | Pollos Hermanos | pollos-hermanos | pollos.local | Starter |

Usuarios creados **en cada tenant**:

| Email | Password | Rol |
|-------|----------|-----|
| `admin@{tenant_id}.com` | password | admin_tenant |
| `worker@{tenant_id}.com` | password | worker |
| `client@{tenant_id}.com` | password | client |

#### `TenantDatabaseSeeder` (automático al crear tenant)

Se ejecuta automáticamente cuando Stancl crea un tenant nuevo vía el pipeline de eventos. Definido en `config/tenancy.php`:

```php
'seeder_parameters' => [
    '--class' => 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder',
],
```

Ejecuta el `RoleSeeder` que crea los roles base: `tenant_admin`, `manager`, `client`.

---

## Roles, permisos y policies

### Paquete: Spatie Laravel Permission

El proyecto usa **spatie/laravel-permission** para gestionar roles y permisos, con guard `tenant`.

### Reset de caché de permisos

En `AppServiceProvider`, cada vez que se inicializa un tenant, se limpia la caché de permisos para evitar conflictos entre schemas:

```php
Event::listen(TenancyInitialized::class, function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
```

### Roles por tenant

Cada schema de tenant tiene sus propios roles (en su tabla `roles`):

| Rol | Guard | Descripción |
|-----|-------|-------------|
| `admin_tenant` | tenant | Administrador del tenant, acceso total |
| `worker` | tenant | Trabajador, acceso a gestión operativa |
| `client` | tenant | Cliente final, acceso limitado |

### Permisos por recurso

#### Users (`users.*`)

| Permiso | admin_tenant | worker | client |
|---------|:---:|:---:|:---:|
| `users.view.all` | ✅ | ✅ | ❌ |
| `users.manage.all` | ✅ | ❌ | ❌ |
| `users.destroy.all` | ✅ | ❌ | ❌ |

#### Vehicles (`vehicles.*`)

| Permiso | admin_tenant | worker | client |
|---------|:---:|:---:|:---:|
| `vehicles.view.all` | ✅ | ✅ | ✅ |
| `vehicles.manage.all` | ✅ | ✅ | ❌ |
| `vehicles.destroy.all` | ✅ | ❌ | ❌ |

#### Incidences (`incidences.*`)

| Permiso | admin_tenant | worker | client |
|---------|:---:|:---:|:---:|
| `incidences.view.all` | ✅ | ✅ | ❌ |
| `incidences.manage.all` | ✅ | ✅ | ❌ |
| `incidences.destroy.all` | ✅ | ❌ | ❌ |

### Policies

Cada recurso tiene su propia Policy en `app/Policies/` (estructura estándar de Laravel). Se crean con:

```bash
php artisan make:policy VehiclePolicy --model=Vehicle
# → Se crea en app/Policies/VehiclePolicy.php
```

Cada policy usa los permisos definidos arriba:

#### `UserPolicy`
- **viewAny**: Requiere `users.view.all`
- **view**: Admin ve todos; usuario solo ve su propio perfil
- **create**: Requiere `users.manage.all`
- **update**: Admin puede editar cualquiera; usuario solo su propio perfil
- **delete**: Admin puede eliminar otros; usuario puede eliminarse a sí mismo

#### `VehiclePolicy`
- **viewAny / view**: Requiere `vehicles.view.all`
- **create**: Requiere `vehicles.manage.all`
- **update**: Requiere `vehicles.manage.all`
- **delete**: Requiere `vehicles.destroy.all`

#### `IncidencePolicy`
- **viewAny**: Cualquier usuario autenticado
- **view**: Admin/worker ven todas; usuario solo ve las que reportó
- **create**: Cualquier usuario autenticado
- **update**: Admin/worker actualizan cualquiera; usuario solo las suyas
- **delete**: Solo con `incidences.destroy.all`
- **resolve**: Solo con `incidences.manage.all`

### Registro de policies

Las policies se registran en `AppServiceProvider` (o en `AuthServiceProvider`) usando `Gate::policy()`:

```php
// app/Providers/AppServiceProvider.php → boot()
Gate::policy(Vehicle::class, VehiclePolicy::class);
Gate::policy(User::class, UserPolicy::class);
Gate::policy(Incidence::class, IncidencePolicy::class);
```

Laravel también soporta **auto-discovery** de policies si sigues la convención de nombres (`app/Policies/VehiclePolicy.php` para el modelo `app/Models/Vehicle.php`), en cuyo caso no necesitas registrarlas manualmente.

---

## Comandos útiles

### Migraciones

```bash
# Migrar schema central (public)
php artisan migrate

# Migrar TODOS los tenants existentes
php artisan tenants:migrate

# Migrar un tenant específico
php artisan tenants:migrate --tenants=1

# Rollback de todos los tenants
php artisan tenants:rollback

# Ver estado de migraciones de un tenant
php artisan tenants:migrate-status --tenants=1
```

### Seeders

```bash
# Ejecutar todo el seeder (central + tenants desde cero)
php artisan db:seed

# Seedear tenants existentes con el TenantDatabaseSeeder
php artisan tenants:seed

# Seedear un tenant específico
php artisan tenants:seed --tenants=1

# Seedear con una clase específica
php artisan tenants:seed --tenants=1 --class=Database\\Seeders\\Tenant\\RoleSeeder
```

### Gestión de tenants

```bash
# Listar todos los tenants
php artisan tenants:list

# Crear un tenant (vía Tinker)
php artisan tinker
>>> \App\Models\Tenant::create(['id' => '3', 'name' => 'Nuevo Tenant', 'slug' => 'nuevo', 'plan_id' => 1, 'status' => 'active']);

# Eliminar un tenant (borra el schema y todos sus datos)
php artisan tinker
>>> \App\Models\Tenant::find('3')->delete();
```

### Reset completo (nuclear)

```bash
# Borrar TODO y empezar de cero
php artisan migrate:fresh --seed
```

> **⚠️ CUIDADO**: Esto borra TODOS los schemas (incluidos los de tenants) y re-ejecuta todas las migraciones y seeders.

### Con Docker

```bash
# Todos los comandos anteriores pero dentro del contenedor
docker-compose exec api php artisan migrate
docker-compose exec api php artisan db:seed
docker-compose exec api php artisan tenants:migrate
docker-compose exec api php artisan migrate:fresh --seed
```

### Verificar schemas en PostgreSQL directamente

```bash
# Conectarse al contenedor de PostgreSQL
docker-compose exec postgres psql -U sims_user -d sims

# Listar todos los schemas
\dn

# Ver tablas de un schema específico
SET search_path TO tenant_1;
\dt

# Ver datos de una tabla del tenant
SELECT * FROM tenant_1.users;

# Ver todos los schemas de tenants
SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'tenant_%';
```

---

## Troubleshooting

### Error: "Missing Tenant ID header"
**Causa**: No estás enviando el header `X-Tenant-ID` en la request.
**Solución**: Agrega `-H "X-Tenant-ID: 1"` a tu request.

### Error: "Tenant not found"
**Causa**: El ID del tenant no existe en `public.tenants`.
**Solución**: Verifica con `SELECT * FROM public.tenants;` que el tenant exista.

### Error: "relation does not exist"
**Causa**: El `search_path` no está apuntando al schema correcto, o las migraciones no se ejecutaron.
**Solución**:
```bash
# Re-ejecutar migraciones del tenant
php artisan tenants:migrate --tenants=1
```

### Permisos no funcionan / cache issues
**Causa**: Spatie cachea permisos y puede haber conflictos entre schemas.
**Solución**: La app ya limpia la caché automáticamente en cada `TenancyInitialized`, pero si persiste:
```bash
php artisan permission:cache-reset
```

### Schema ya existe al re-seedear
**Causa**: Intentas crear un tenant cuyo schema ya existe.
**Solución**: Usa `migrate:fresh --seed` para un reset completo, o borra el tenant primero.

### PostGIS no disponible
**Causa**: La extensión postgis no está instalada.
**Solución**: Verifica que uses la imagen `postgis/postgis:15-3.3` en Docker, o instala PostGIS manualmente en tu PostgreSQL local.

### Token de autenticación no funciona
**Causa**: El middleware `AuthenticateTenant` busca el token directamente en el schema del tenant usando queries raw.
**Solución**: Asegúrate de que:
1. El token fue generado en el contexto del tenant correcto.
2. El header `X-Tenant-ID` coincide con el tenant donde se creó el token.
3. La tabla `personal_access_tokens` existe en el schema del tenant.

---

## Referencia rápida de la API

### Rutas centrales (`/api/central/`)

```
POST   /api/central/login     → Login de global admin
POST   /api/central/logout    → Logout (requiere auth:central)
GET    /api/central/me        → Perfil del admin (requiere auth:central)
```

### Rutas de tenant (`/api/tenant/`) — requieren header `X-Tenant-ID`

```
POST   /api/tenant/register   → Registro de usuario
POST   /api/tenant/login      → Login de usuario

# Requieren auth (Bearer token):
GET    /api/tenant/user        → Perfil del usuario
GET    /api/tenant/profile     → Perfil del usuario
PATCH  /api/tenant/profile     → Actualizar perfil
GET    /api/tenant/roles       → Listar roles disponibles
POST   /api/tenant/logout      → Logout

# CRUD Users
GET    /api/tenant/users
POST   /api/tenant/users
GET    /api/tenant/users/{id}
PUT    /api/tenant/users/{id}
DELETE /api/tenant/users/{id}

# CRUD Vehicles
GET    /api/tenant/vehicles
POST   /api/tenant/vehicles
GET    /api/tenant/vehicles/{id}
PUT    /api/tenant/vehicles/{id}
DELETE /api/tenant/vehicles/{id}

# CRUD Incidences
GET    /api/tenant/incidences
POST   /api/tenant/incidences
GET    /api/tenant/incidences/{id}
PUT    /api/tenant/incidences/{id}
DELETE /api/tenant/incidences/{id}
GET    /api/tenant/incidences-trashed
```
