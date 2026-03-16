# Sistema de Tenants i Dominis

Documentació tècnica del sistema multi-tenant de SIMSback.  
Destinada a desenvolupadors nous i col·laboradors que hagin de treballar o ampliar el mòdul.

---

## Índex

1. [Visió general](#1-visió-general)
2. [Arquitectura de la base de dades](#2-arquitectura-de-la-base-de-dades)
3. [Cicle de vida d'un tenant](#3-cicle-de-vida-dun-tenant)
4. [Variables d'entorn](#4-variables-dentorn)
5. [Crear un tenant](#5-crear-un-tenant)
6. [Autenticació](#6-autenticació)
7. [Rutes: central vs. tenant](#7-rutes-central-vs-tenant)
8. [Migrations de tenant](#8-migrations-de-tenant)
9. [Seeders de tenant](#9-seeders-de-tenant)
10. [Afegir un nou mòdul (model + migració + ruta)](#10-afegir-un-nou-mòdul-model--migració--ruta)
11. [Configuració local (DNS i ports)](#11-configuració-local-dns-i-ports)
12. [Pas a producció](#12-pas-a-producció)
13. [Troubleshooting](#13-troubleshooting)
14. [Tests dels tenants](#14-tests-dels-tenants)

---

## 1. Visió general

El projecte utilitza el paquet **[stancl/tenancy v3](https://tenancyforlaravel.com/)** amb la estratègia **PostgreSQL Schema**.

```
                    ┌─────────────────────────────────────┐
                    │          Base de dades PostgreSQL    │
                    │                                      │
                    │  schema: public  (dades centrals)    │
                    │  ├─ tenants                          │
                    │  ├─ domains                          │
                    │  ├─ superadmins                      │
                    │  └─ personal_access_tokens           │
                    │                                      │
                    │  schema: tenant_empresa1             │
                    │  ├─ users                            │
                    │  ├─ vehicles                         │
                    │  ├─ reservations                     │
                    │  ├─ tickets                          │
                    │  ├─ geofences                        │
                    │  └─ vehicle_geofence_logs            │
                    │                                      │
                    │  schema: tenant_empresa2             │
                    │  └─ (idem)                           │
                    └─────────────────────────────────────┘
```

Cada tenant és **completament aïllat**: les seves dades mai es barregen amb les d'un altre tenant ni amb les dades centrals.

**Identificació de tenant**: per subdomini.

```
http://empresa1.lvh.me:8000/api/v1/...    → tenant = empresa1
http://empresa2.lvh.me:8000/api/v1/...    → tenant = empresa2
http://localhost:8000/api/v1/superadmin/... → central (SuperAdmin)
```

---

## 2. Arquitectura de la base de dades

### Schema `public` (central)

| Taula | Contingut |
|-------|-----------|
| `tenants` | Llista de tenants |
| `domains` | Dominis de cada tenant |
| `superadmins` | Usuaris globals |
| `personal_access_tokens` | Tokens Sanctum (SuperAdmins) |

### Schema `tenant_{id}` (per tenant)

| Taula | Contingut |
|-------|-----------|
| `users` | Usuaris del tenant |
| `vehicles` | Vehicles gestionats |
| `reservations` | Reserves |
| `tickets` | Incidències |
| `geofences` | Zones geogràfiques |
| `vehicle_geofence_logs` | Historial de geofences |
| `personal_access_tokens` | Tokens Sanctum (usuaris) |
| `sessions` | Sessions web |

### Model `Tenant`

Els camps es guarden com a **atributs directes**:

```php
Tenant::create([
    'id' => 'empresa1',
    'name' => 'Empresa Un',
    'admin_name' => 'Joan Garcia',
    'admin_email' => 'joan@empresa1.com',
    'admin_password' => 'secretpassword',
]);

// Accés
$tenant->name;
$tenant->admin_email;
$tenant->admin_password; // null després de seed
```

---

## 3. Cicle de vida d'un tenant

Quan un SuperAdmin crida `POST /api/v1/superadmin/tenants`, això passa en ordre:

```
1. Validació del request
2. Tenant::create()
        │
        └─► [Event: TenantCreated] pipeline automàtic (TenancyServiceProvider)
                ├─ CreateDatabase  → crea schema tenant_{id}
                └─ MigrateDatabase → executa totes les migrations de database/migrations/tenant/
3. $tenant->domains()->create()   → registra el subdomini a la taula domains
4. SeedDatabase::dispatchSync()   → executa TenantDatabaseSeeder (crea admin + dades demo)
5. Resposta 201 amb URL d'accés
```

> **Nota important**: el seed s'executa **explícitament** al controlador (pas 4), NO dins el pipeline del pas 2. Això és intencionat: si el seed falla, el tenant i el domini ja existeixen i la resposta 201 s'envia igualment. El error queda loggejat a `storage/logs/laravel.log`.

Quan s'elimina un tenant (`DELETE /api/v1/superadmin/tenants/{id}`):

```
TenantDeleted → DeleteDatabase → elimina el schema tenant_{id} sencer
```

---

## 4. Variables d'entorn

| Variable | Exemple local | Exemple producció | Descripció |
|----------|--------------|-------------------|------------|
| `APP_URL` | `http://localhost:8000` | `https://api.sims.com` | URL base de l'app central |
| `TENANT_BASE_DOMAIN` | `lvh.me` | `sims.com` | Domini arrel dels subdominis de tenant |
| `APP_DOMAIN` | *(no cal en local)* | `api.sims.com` | Domini central (afegit a `central_domains`) |
| `DB_CONNECTION` | `pgsql` | `pgsql` | Driver de BD |
| `DB_HOST` | `sims_postgres` | `*` | Host PostgreSQL |

### Com es generen les URLs

**Local:**
```
APP_URL=http://localhost:8000
TENANT_BASE_DOMAIN=lvh.me
→ URL tenant: http://empresa1.lvh.me:8000
```

**Producció:**
```
APP_URL=https://api.sims.com
TENANT_BASE_DOMAIN=sims.com
APP_DOMAIN=api.sims.com
→ URL tenant: https://empresa1.sims.com
```

---

## 5. Crear un tenant

**Endpoint:** `POST /api/v1/superadmin/tenants`  
**Auth:** Bearer token (SuperAdmin)

```json
{
    "id":             "empresa1",
    "name":           "Empresa Un, S.L.",
    "admin_name":     "Joan Garcia",
    "admin_email":    "joan@empresa1.com",
    "admin_password": "secretpassword"
}
```

**Camps opcionals:** `admin_name`, `admin_email`, `admin_password` es generen automàticament si falten.

**Resposta 201:**
```json
{
    "message": "Tenant created successfully",
    "tenant": {
        "id": "empresa1",
        "name": "Empresa Un, S.L.",
        "admin_name": "Joan Garcia",
        "admin_email": "joan@empresa1.com",
        "domains": [{ "domain": "empresa1" }]
    },
    "access": {
        "url": "http://empresa1.lvh.me:8000",
        "admin_email": "joan@empresa1.com"
    }
}
```

---

## 6. Autenticació

### SuperAdmin (central)

| Endpoint | Mètode |
|----------|--------|
| `/api/v1/superadmin/auth/login` | POST |
| `/api/v1/superadmin/auth/me` | GET |
| `/api/v1/superadmin/auth/logout` | POST |

Middlewares: `auth:sanctum`, `ensure.superadmin`

### Usuari de tenant

| Endpoint | Mètode |
|----------|--------|
| `http://{id}.lvh.me:8000/api/v1/auth/register` | POST |
| `http://{id}.lvh.me:8000/api/v1/auth/login` | POST |
| `http://{id}.lvh.me:8000/api/v1/auth/me` | GET |
| `http://{id}.lvh.me:8000/api/v1/auth/logout` | POST |

El tenant es detecta pel subdomini. Les operacions de BD van automàticament al schema `tenant_{id}`.

---

## 7. Rutes: central vs. tenant

| Fitxer | Àmbit | Middlewares |
|--------|-------|------------|
| `routes/api.php` | SuperAdmin central | `auth:sanctum`, `ensure.superadmin` |
| `routes/tenant.php` | Tenant per subdomini | `InitializeTenancyBySubdomain`, `PreventAccessFromCentralDomains` |
| `routes/web.php` | Públic | cap |

> **Ordre important a `routes/tenant.php`:** `InitializeTenancyBySubdomain` primer, després `PreventAccessFromCentralDomains`.

---

## 8. Migrations de tenant

Totes les migrations de tenant es guarden a `database/migrations/tenant/`  
Les migrations de `database/migrations/` afecten només al schema central `public`.

### Crear una migració

```bash
php artisan make:migration create_xxxx_table --path=database/migrations/tenant
```

S'executarà automàticament en cada tenant nou. Per aplicar-la a tenants existents:

```bash
docker compose exec api php artisan tenants:migrate
# O per un tenant concret:
docker compose exec api php artisan tenants:migrate --tenants=empresa1
```

---

## 9. Seeders de tenant

El seeder `database/seeders/TenantDatabaseSeeder.php` s'executa automàticament quan es crea un tenant (via API).

Crea:
- 1 usuari admin (credencials indicades en la creació)
- 5 usuaris rol `user`
- 8 vehicles
- 6 reserves
- 10 tickets
- 5 geofences

Després del seed, `admin_password` es descarta per seguretat.

### Re-executar el seed

```bash
docker compose exec api php artisan tenants:seed --tenants=empresa1
```

> ⚠️ Crea dades addicionals (no les esborra). Només en desarrollo.

---

## 10. Afegir un nou mòdul (model + migració + ruta)

### 1. Migració (a `database/migrations/tenant/`)

```bash
php artisan make:migration create_xxxx_table --path=database/migrations/tenant
```

### 2. Model

```bash
php artisan make:model XxxModel
```

> **No cal indicar connexió ni schema.** El sistema commuta automàticament.

### 3. Controlador

```bash
php artisan make:controller Api/XxxController --api
```

Implementa `index`, `store`, `show`, `update`, `destroy`.

### 4. Ruta a `routes/tenant.php`

```php
Route::apiResource('xxxx', XxxController::class);
```

### 5. Aplicar a tenants existents

```bash
docker compose exec api php artisan tenants:migrate
```

---

## 11. Configuració local (DNS i ports)

**URL dels tenants:** `http://{id}.lvh.me:8000/api/...`

`lvh.me` és un domini públic el DNS del qual sempre resol a `127.0.0.1`. **No cal configurar res.**

Si no tens connexió a internet, afegeix a `/etc/hosts`:
```bash
echo "127.0.0.1 empresa1.lvh.me" | sudo tee -a /etc/hosts
```

---

## 12. Pas a producció

### Variables d'entorn

```dotenv
APP_URL=https://api.sims.com
TENANT_BASE_DOMAIN=sims.com
APP_DOMAIN=api.sims.com
```

Això genera automàticament:
- URL tenant: `https://empresa1.sims.com`
- `central_domains`: `['127.0.0.1', 'localhost', 'sims.com', 'api.sims.com']`

### Infraestructura necessària

- **DNS wildcard:** `*.sims.com → IP del servidor`
- **SSL wildcard:** `certbot -d sims.com -d *.sims.com`
- **Nginx/proxy:** Forwarding del port 80/443 al contenidor API
- **Migracions:** `php artisan migrate --force` + `php artisan tenants:migrate --force`

> El codi NO necessita canvis per a producció.

---

## 13. Troubleshooting

### Error: `relation "tenants" does not exist`

Les migracions centrals no s'han executat.

```bash
docker compose exec api php artisan migrate --force
```

### Error: `Could not find driver` / connexió a BD

Comprova que el contenidor `sims_postgres` estigui actiu i que les variables `DB_*` del `.env` siguin correctes.

```bash
docker compose ps
docker compose logs sims_postgres
```

### El subdomini no resol (ERR_NAME_NOT_RESOLVED)

Comprova que tens connexió a internet (el DNS de `lvh.me` és extern). Si treballes offline, afegeix l'entrada a `/etc/hosts` (veure §11).

### `php artisan tinker` falla amb error de permisos (en Docker)

```bash
# Afegir -e HOME=/tmp
docker compose exec -e HOME=/tmp api php artisan tinker
```

### El seed ha fallat però el tenant existeix

El tenant i el domini s'han creat correctament. Per re-sembrar:

```bash
docker compose exec api php artisan tenants:seed --tenants={id_tenant}
```

Revisa el log per veure el motiu del fallo original:

```bash
tail -50 storage/logs/laravel.log
```

### Eliminar un tenant i tornar-lo a crear (en desenvolupament)

```bash
# Via API
DELETE http://localhost:8000/api/v1/superadmin/tenants/empresa1
# Authorization: Bearer {token_superadmin}

# Tornar a crear
POST http://localhost:8000/api/v1/superadmin/tenants
```

Eliminar el tenant **elimina el schema PostgreSQL sencer** i tots els seus recursos.
---

## 14. Tests dels tenants

La suite de tests es troba a `tests/Tenant/` i cobreix: login de SuperAdmin, creació/eliminació de tenants, login d'usuari admin, tokens creuats, aïllament de dades.

### Executar els tests

```bash
docker exec sims_api ./vendor/bin/phpunit --configuration=phpunit.tenants.xml
```

> S'han d'executar dins del contenidor (PHP de l'host no té `pdo_pgsql`).

### Per a CI (GitHub Actions)

El workflow `.github/workflows/tenant-tests.yml` aixeca un service container de PostgreSQL amb `pdo_pgsql` directament al runner.
