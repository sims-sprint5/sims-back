# SIMS Back — Shared Vehicle Management System API

Laravel 12 + PostgreSQL multi-tenant REST API with IoT vehicle control.

## Tech Stack

| Technology | Version | Purpose |
|-----------|---------|---------|
| PHP | ^8.4 | Language |
| Laravel | ^12.0 | Backend framework |
| Laravel Sanctum | ^4.0 | Token-based authentication |
| PostgreSQL + PostGIS | 15 | Relational database |
| stancl/tenancy | ^3.x | Multi-tenant (PostgreSQL schemas) |
| Docker + nginx | Latest | Containerisation |

## Quick Start (Local)

```bash
git clone <repo-url> && cd sims-back-1
cp .env.example .env

# Adjust .env: APP_KEY, DB_*, APP_URL
docker compose up -d --build

docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --force
docker compose exec api php artisan db:seed --force
docker compose exec api php artisan tenants:migrate --force
```

API available at `http://localhost:8000`  
PgAdmin at `http://localhost:5050`

## Key Endpoints

| Group | Base path |
|-------|-----------|
| SuperAdmin auth | `POST /api/v1/superadmin/auth/login` |
| Tenant management | `/api/v1/superadmin/tenants` |
| Tenant auth | `POST /api/v1/auth/login` (via subdomain) |
| Vehicles | `/api/v1/vehicles` |
| Reservations | `/api/v1/reservations` |
| Vehicle control (IoT) | `POST /api/v1/reservations/{id}/vehicle/on|off` |

All protected routes require `Authorization: Bearer {token}`.

## Multi-Tenant Architecture

Each organisation (tenant) gets an isolated PostgreSQL schema (`tenant_{id}`), identified by subdomain:

```
http://company1.lvh.me:8000/api/v1/...     → tenant = company1
http://localhost:8000/api/v1/superadmin/... → central (SuperAdmin)
```

See [`~/.github/TENANTS_AND_DOMAINS.md`](~/.github/TENANTS_AND_DOMAINS.md) for full documentation.

## Docker Services

| Container | Role | Port |
|-----------|------|------|
| `sims_api` | PHP-FPM (Laravel) | internal |
| `sims_api_web` | nginx reverse proxy | 8000 |
| `sims_postgres` | PostgreSQL 15 | 5432 |
| `sims_pgadmin` | PgAdmin 4 | 5050 |
| `iot-api` | FastAPI IoT subsystem | 8088 |

All containers share the external Docker network `sims_network`.  
Create it once with: `docker network create sims_network`

## Deployment

Push to `main` triggers GitHub Actions: lint → test → deploy to production server.

See [`~/.github/SETUP_PRODUCTION.md`](~/.github/SETUP_PRODUCTION.md) for full setup.  
See [`~/.github/DEBUGGING.md`](~/.github/DEBUGGING.md) for troubleshooting.
