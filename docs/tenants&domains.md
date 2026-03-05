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
| `tenants` | Llista de tenants. `data` és un JSON amb `name`, `admin_email`, etc. |
| `domains` | Un registre per tenant: `empresa1.localhost` o `empresa1.sims.com` |
| `superadmins` | Usuaris globals (no pertanyen a cap tenant) |
| `personal_access_tokens` | Tokens Sanctum dels SuperAdmins |

### Schema `tenant_{id}` (per tenant)

| Taula | Contingut |
|-------|-----------|
| `users` | Usuaris del tenant (rol: `admin`, `manager`, `user`, `technical`) |
| `vehicles` | Vehicles gestionats pel tenant |
| `reservations` | Reserves de vehicles |
| `tickets` | Incidències i suport |
| `geofences` | Zones geogràfiques |
| `vehicle_geofence_logs` | Historial d'entrades/sortides de geofences |
| `personal_access_tokens` | Tokens Sanctum dels usuaris del tenant |
| `sessions` | Sessions web del tenant |

### Model `Tenant`

El camp `data` és un JSON que emmagatzema metadades:

```php
// ✅ Correcte: 'name' dins de 'data'
Tenant::create([
    'id'   => 'empresa1',
    'data' => [
        'name'        => 'Empresa Un',
        'admin_email' => 'admin@empresa1.localhost',
    ],
]);

// ❌ Mai fer-ho així: 'name' es perd silenciosament
Tenant::create([
    'id'   => 'empresa1',
    'name' => 'Empresa Un',   // ← es sobreescriu per 'data', es perd
    'data' => [...],
]);
```

Accessors disponibles al model `Tenant`:

```php
$tenant->getName();       // string|null  — el nom de l'empresa
$tenant->getAdminEmail(); // string|null  — l'email de l'admin
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

### Com s'usen `APP_URL` i `TENANT_BASE_DOMAIN`

```
APP_URL=http://localhost:8000
TENANT_BASE_DOMAIN=lvh.me

→ URL tenant generat:  http://empresa1.lvh.me:8000
→ Domini a la BD:       empresa1
```

```
APP_URL=https://api.sims.com
TENANT_BASE_DOMAIN=sims.com

→ URL tenant generat:  https://empresa1.sims.com
→ Domini a la BD:       empresa1.sims.com
```

### `central_domains` (config/tenancy.php)

Aquesta llista defineix quins dominis pertanyen a la part **central** (no tenant).  
Es construeix automàticament a partir de les variables d'entorn:

```php
'central_domains' => array_filter([
    '127.0.0.1',
    'localhost',
    env('TENANT_BASE_DOMAIN'),  // evita redirecció infinita si coincideix amb el base
    env('APP_DOMAIN'),           // domini de l'API central en producció
]),
```

---

## 5. Crear un tenant

### Via API (Postman / frontend)

```
POST http://localhost:8000/api/v1/superadmin/tenants
Authorization: Bearer {token_superadmin}
Content-Type: application/json

{
    "id":             "empresa1",
    "name":           "Empresa Un, S.L.",
    "admin_name":     "Joan Garcia",
    "admin_email":    "joan@empresa1.com",
    "admin_password": "secretpassword"
}
```

`admin_name`, `admin_email` i `admin_password` són opcionals.  
Si no s'especifiquen, es generen automàticament a partir de l'`id`.

**Resposta 201:**
```json
{
    "message": "Tenant creat correctament",
    "tenant": {
        "id": "empresa1",
        "data": { "name": "Empresa Un, S.L.", "admin_email": "joan@empresa1.com" },
        "domains": [{ "domain": "empresa1" }]
    },
    "access": {
        "url": "http://empresa1.lvh.me:8000",
        "admin_email": "joan@empresa1.com"
    }
}
```

### Via Artisan (terminal / scripts de deploy)

```bash
# Dins del contenidor Docker
docker compose exec api php artisan tenant:create empresa1 "Empresa Un, S.L." \
    --admin-email=joan@empresa1.com \
    --admin-password=secretpassword \
    --admin-name="Joan Garcia"
```

Output esperat:
```
Creant tenant 'Empresa Un, S.L.' (empresa1)...

  ✅ Schema PostgreSQL: tenant_empresa1
  ✅ Migracions executades
  ✅ Dades seed creades
  ✅ Domini: empresa1

+----------+-------------------------------+
| Detall   | Valor                         |
+----------+-------------------------------+
| URL      | http://empresa1.lvh.me:8000   |
| Admin    | joan@empresa1.com             |
| Password | secretpassword                |
+----------+-------------------------------+
```

---

## 6. Autenticació

Hi ha **dos sistemes d'autenticació independents**:

### 6.1 SuperAdmin (central)

| Endpoint | Mètode | Auth |
|----------|--------|------|
| `/api/v1/superadmin/auth/login` | POST | — |
| `/api/v1/superadmin/auth/me` | GET | Bearer token |
| `/api/v1/superadmin/auth/logout` | POST | Bearer token |

Els tokens es validen per dos middlewares apilats:
- `auth:sanctum` → verifica que el token sigui vàlid
- `ensure.superadmin` → verifica que el model tokenable sigui un `SuperAdmin` (no un `User` de tenant)

### 6.2 Usuari de tenant

| Endpoint | Mètode | Auth |
|----------|--------|------|
| `http://{id}.lvh.me:8000/api/auth/register` | POST | — |
| `http://{id}.lvh.me:8000/api/auth/login` | POST | — |
| `http://{id}.lvh.me:8000/api/auth/me` | GET | Bearer token |
| `http://{id}.lvh.me:8000/api/auth/logout` | POST | Bearer token |

El tenant es detecta automàticament pel subdomini. Un cop inicialitzat el context del tenant, totes les operacions de BD es fan contra el schema `tenant_{id}`.

---

## 7. Rutes: central vs. tenant

| Fitxer | Àmbit | Middleware clau |
|--------|-------|-----------------|
| `routes/api.php` | Central (SuperAdmin) | `auth:sanctum` + `ensure.superadmin` |
| `routes/tenant.php` | Tenant (per subdomini) | `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains` |
| `routes/web.php` | Públic central | cap |

**Ordre dels middlewares a `routes/tenant.php`** (important no canviar):

```php
Route::middleware([
    'api',
    InitializeTenancyBySubdomain::class,  // primer: detecta i inicialitza el tenant
    PreventAccessFromCentralDomains::class, // segon: bloqueja dominis centrals
])->...
```

---

## 8. Migrations de tenant

Totes les migrations que han de córrer **dins de cada tenant** van a:

```
database/migrations/tenant/
```

Les migrations de la carpeta arrel (`database/migrations/`) NOMÉS afecten la BD central (`public` schema).

### Crear una nova migració de tenant

```bash
# Crea la migració manualment a la carpeta tenant/
php artisan make:migration create_xxxx_table
# Mou el fitxer generat a database/migrations/tenant/
mv database/migrations/YYYY_..._create_xxxx_table.php database/migrations/tenant/
```

O directament:

```bash
php artisan make:migration create_xxxx_table --path=database/migrations/tenant
```

> La migració s'executarà automàticament per a cada tenant nou que es creï.  
> Per aplicar-la a tenants **ja existents**, cal executar:

```bash
docker compose exec api php artisan tenants:migrate
# O per un tenant concret:
docker compose exec api php artisan tenants:migrate --tenants=empresa1
```

---

## 9. Seeders de tenant

El seeder de tenant és `database/seeders/TenantDatabaseSeeder.php`.  
S'executa automàticament quan es crea un tenant nou (via API o Artisan).

Crea per defecte:
- 1 usuari **admin** (amb les credencials indicades en la creació)
- 5 usuaris amb rol `user` (factories)
- 8 vehicles
- 6 reserves
- 10 tickets
- 5 geofences

Un cop el seed acaba, **elimina la contrasenya del camp `data`** del tenant per seguretat.

### Re-executar el seed d'un tenant existent

```bash
docker compose exec api php artisan tenants:seed --tenants=empresa1
```

> ⚠️ Això crea dades addicionals, **no esborra les existents**. Fes-ho només en entorns de desenvolupament.

---

## 10. Afegir un nou mòdul (model + migració + ruta)

Exemple: afegir un mòdul `MaintenanceRecord` (registres de manteniment de vehicles).

### Pas 1 — Migració de tenant

```bash
php artisan make:migration create_maintenance_records_table --path=database/migrations/tenant
```

```php
// database/migrations/tenant/YYYY_..._create_maintenance_records_table.php
Schema::create('maintenance_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
    $table->text('description');
    $table->date('date');
    $table->timestamps();
});
```

### Pas 2 — Model

```bash
php artisan make:model MaintenanceRecord
```

```php
// app/Models/MaintenanceRecord.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = ['vehicle_id', 'description', 'date'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }
}
```

> ⚠️ No cal indicar connexió de BD ni schema. El sistema commuta automàticament al schema del tenant actiu.

### Pas 3 — Controlador

```bash
php artisan make:controller Api/MaintenanceRecordController --api
```

Implementa els mètodes `index`, `store`, `show`, `update`, `destroy` dins la classe.

### Pas 4 — Ruta a `routes/tenant.php`

```php
// Afegeix dins del grup auth:sanctum existent
Route::apiResource('maintenance-records', MaintenanceRecordController::class);
```

### Pas 5 — Aplicar la migració als tenants existents

```bash
docker compose exec api php artisan tenants:migrate
```

---

## 11. Configuració local (DNS i ports)

El projecte usa `lvh.me` com a domini base local. `lvh.me` és un domini públic gratuït el DNS del qual sempre resol qualsevol subdomini a `127.0.0.1`:

```
*.lvh.me  →  127.0.0.1  (via DNS públic, sense configurar res)
```

**No cal configurar cap fitxer de hosts ni cap servidor DNS.** Qualsevol màquina amb connexió a internet pot resoldre `empresa1.lvh.me` automàticament.

### `.env` configurat per defecte

```dotenv
APP_URL=http://localhost:8000
TENANT_BASE_DOMAIN=lvh.me
```

Les URLs dels tenants queden: `http://empresa1.lvh.me:8000/api/...`

### Si no hi ha connexió a internet

Com a alternativa, afegeix les entrades a mà a `/etc/hosts` (Linux/Mac) o `C:\Windows\System32\drivers\etc\hosts` (Windows):

```bash
echo "127.0.0.1 empresa1.lvh.me" | sudo tee -a /etc/hosts
```

Cal una línia per cada tenant. **No recomanat per a ús diari.**

### Port

El servidor de l'API escolta al port **8000** en local (mapejat des del contenidor Docker).  
L'URL d'un tenant en local té sempre la forma: `http://{id}.lvh.me:8000/api/...`

---

## 12. Pas a producció

### Canvis al `.env`

```dotenv
APP_URL=https://api.sims.com
TENANT_BASE_DOMAIN=sims.com
APP_DOMAIN=api.sims.com
```

Amb aquests tres canvis, el codi genera automàticament:
- Domini de tenant: `empresa1.sims.com`
- URL d'accés: `https://empresa1.sims.com`
- `central_domains` inclou: `127.0.0.1`, `localhost`, `sims.com`, `api.sims.com`

### Infraestructura necessària (fora del codi)

| Element | Detall |
|---------|--------|
| **DNS wildcard** | `*.sims.com → IP del servidor` (configurat al proveïdor DNS) |
| **SSL wildcard** | `certbot --dns ... -d sims.com -d *.sims.com` |
| **Nginx/proxy** | Escoltar ports 80/443, passar al contenidor API per header `Host:` |
| **`APP_KEY`** | Generar un de nou: `php artisan key:generate` |
| **Migracions** | `php artisan migrate --force` + `php artisan tenants:migrate --force` |

> El codi de l'aplicació **no necessita cap modificació** per al pas a producció.

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

La suite de tests d'integració es troba a `tests/Tenant/` i cobreix el cicle de vida complet: login de SuperAdmin, creació i eliminació de tenants, login d'usuari admin, rebuig de tokens creuats i aïllament de dades entre tenants.

### Executar els tests

```bash
docker exec sims_api ./vendor/bin/phpunit --configuration=phpunit.tenants.xml
```

Els tests s'han d'executar **dins del contenidor** perquè el PHP de l'host no té l'extensió `pdo_pgsql`. La configuració específica dels tests de tenant és a `phpunit.tenants.xml`.

### Per a CI (GitHub Actions)

El workflow `.github/workflows/tenant-tests.yml` aixeca un service container de PostgreSQL i instal·la PHP amb `pdo_pgsql` directament al runner, sense necessitat de Docker Compose.

### Problemes de state leak en tests (ja corregits)

Laravel reutilitza el mateix procés PHP per a totes les peticions HTTP simulades en una classe de tests. Això crea dos problemes que no existeixen en producció (on cada petició té el seu propi procés PHP-FPM):

| Problema | Causa | Solució aplicada |
|----------|-------|------------------|
| Connexió de BD del tenant anterior no es revertia | `InitializeTenancyBySubdomain` mai crida `tenancy()->end()` en retornar la resposta | `call()` sobreescrit al `TestCase` crida `tenancy()->end()` després de cada petició |
| Sanctum retornava sempre el mateix usuari cachesat | `AuthManager` cacheja el guard i l'usuari resolts entre peticions | `Auth::forgetGuards()` es crida després de cada petició per netejar la caché |

Aquestes correccions es fan a `TenantLifecycleTest::call()` i **no afecten el comportament en producció**.
