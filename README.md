# SIMS Backend

**Intelligent Sustainable Mobility System** - API backend for EV fleet management.

## Overview

SIMS is a **backend-only** B2B SaaS platform for managing electric vehicle fleets with:

- 🏢 **Multi-tenancy** - Schema-per-tenant isolation with `stancl/tenancy`
- 📍 **Geospatial** - PostGIS for geofencing and location tracking
- 💳 **Multi-gateway payments** - Stripe, PayPal, Square support
- 🎯 **Feature gating** - Plan-based access control (Basic, Pro, Enterprise)
- 🔐 **Token authentication** - Laravel Sanctum for API access

**Tech Stack:** Laravel 12 + PostgreSQL 15 + PostGIS + Redis

## Quick Start

### Docker Setup (Recommended)

```bash
# Clone and install dependencies
git clone <repo-url> sims-back
cd sims-back
composer install

# Configure environment
cp .env.example .env

# Generate APP_KEY
docker-compose run api php artisan key:generate

# Build and start Docker containers
docker-compose up --build

# In another terminal, run central migrations
docker-compose exec api php artisan migrate:fresh --seed 



# If already data in database use this for wipe de DB
docker-compose exec api php artisan db:wipe-all 

# Or force without confirmation 
docker-compose exec api php artisan db:wipe-all --force

```

## Docker Configuration

### Multi-Container Architecture

The project uses Docker Compose to orchestrate **2 containers**:

1. **PostgreSQL 15 + PostGIS** - Database service
   - Image: `postgis/postgis:15-3.3`
   - Container: `sims_postgres`
   - Port: `localhost:5432`
   - Data persistence: `postgres_data` volume
   - Schema: `public` (central) + multiple `tenant_*` schemas (per-tenant)

2. **Laravel API** - PHP-FPM application server
   - Image: Built from `Dockerfile` (PHP 8.2)
   - Container: `sims_api`
   - Port: `localhost:8000`
   - Live code sync: `.:/var/www/html`

### Docker Files

- **`Dockerfile`** - PHP 8.2 FPM image with Laravel dependencies, PostgreSQL driver, and Composer
- **`docker-compose.yml`** - Orchestrates PostgreSQL and Laravel containers with networking, volumes, and multi-tenancy configuration
- **`.env.example`** - Docker-ready environment configuration template
- **`.dockerignore`** - Optimizes Docker build context

### How Containers Connect

```
Your Machine (localhost)
    ↓
Docker Bridge Network (sims_network)
    ├─ api (localhost:8000) → postgres:5432
    └─ postgres (localhost:5432) ← api
```

- **Between containers**: API uses `DB_HOST=postgres` (Docker DNS resolves the service name)
- **From your machine**: Access via `localhost:8000` (API) and `localhost:5432` (Database)
- **Network**: Both containers connected via `sims_network` bridge driver
- **Dependency**: API waits for PostgreSQL health check before starting
- **Multi-tenancy**: Each tenant gets its own schema (`tenant_madrid_mobility`, `tenant_barcelona_escooters`, etc.)

### Docker Commands

```bash
# Build and start containers
docker-compose up --build

# View logs
docker-compose logs -f api
docker-compose logs -f postgres

# Run artisan commands in API container
docker-compose exec api php artisan migrate:fresh --seed
docker-compose exec api php artisan tinker
docker-compose exec api php artisan route:list
docker-compose exec api php artisan optimize:clear

# Access database directly
docker-compose exec postgres psql -U sims_user -d sims

# Stop containers
docker-compose down

# Remove volumes (delete database data)
docker-compose down -v
```

## Documentation

Full documentation is available in the [`docs/`](docs/) directory:

- **[Getting Started](docs/guides/getting-started.md)** - Setup and installation
- **[Laravel Technical Guide](docs/guides/laravel-technical-guide.md)** - Laravel concepts for PHP/Python developers
- **[Architecture Overview](docs/architecture/overview.md)** - System design
- **[Multi-tenancy](docs/architecture/multi-tenancy.md)** - Tenant isolation strategy (Schema per Tenant)
- **[Plans & Features](docs/architecture/plans-features.md)** - Feature gating system
- **[Data Model](docs/database/data-model.md)** - Database schema and relationships

## Project Structure

```
sims-back/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/  # API controllers
│   │   └── Middleware/       # Custom middleware
│   ├── Models/
│   │   ├── Central/          # Platform models (plans, tenants)
│   │   └── Tenant/           # Tenant-scoped models (users, vehicles)
│   └── Services/             # Business logic
├── config/
│   ├── tenancy.php           # Multi-tenancy configuration
│   └── ...                   # Other config files
├── database/
│   ├── factories/            # Model factories
│   ├── migrations/
│   │   ├── central/          # Platform schema (users, tenants, plans)
│   │   └── tenant/           # Tenant schemas (vehicles, bookings, geofences)
│   └── seeders/              # Database seeders
├── docs/                     # Documentation
├── Dockerfile                # PHP 8.2 FPM image
├── docker-compose.yml        # Multi-container orchestration
├── .env.example              # Docker environment template
├── .dockerignore             # Docker build optimization
└── routes/
    ├── api.php               # Central API routes
    └── tenant.php            # Tenant-scoped routes
```

## Multi-Tenancy: Schema per Tenant

Each tenant gets its own **PostgreSQL schema** isolated from others:

```
PostgreSQL Database (sims)
    ├─ public schema (Central Platform)
    │   ├─ tenants (list of all tenants)
    │   ├─ plans (subscription plans)
    │   ├─ features (available features)
    │   └─ users (global admins)
    │
    ├─ tenant_madrid_mobility schema
    │   ├─ users (Madrid Mobility users)
    │   ├─ vehicles (Madrid Mobility vehicles)
    │   ├─ bookings (Madrid Mobility bookings)
    │   ├─ geofences (Geofencing data)
    │   └─ vehicle_locations (Real-time locations)
    │
    ├─ tenant_barcelona_escooters schema
    │   └─ (same structure as above)
    │
    └─ tenant_malaga_shuttles schema
        └─ (same structure as above)
```

### Demo Tenants

After running the setup, you can test with:

| Tenant | Domain | Plan | Admin Email |
|--------|--------|------|-------------|
| Madrid Mobility | `madrid-mobility.sims.app` | Pro | `carlos@madrid-mobility.com` |
| Barcelona E-Scooters | `barcelona-escooters.sims.app` | Enterprise | `marta@bcn-scooters.com` |
| Malaga Shuttles | `malaga-shuttles.sims.app` | Basic (trial) | `antonio@malaga-shuttles.es` |

Default password for all demo users: `password`

## Development

### With Docker

```bash
# Start containers
docker-compose up -d

# View logs
docker-compose logs -f api
docker-compose logs -f postgres

# Run artisan commands
docker-compose exec api php artisan migrate
docker-compose exec api php artisan tenants:list
docker-compose exec api php artisan tinker

# Create new tenant
docker-compose exec api php artisan tenants:create <name> <domain>

# Seed specific tenant
docker-compose exec api php artisan tenants:seed --tenant=<tenant_id> --class=DemoTenantSeeder

# Stop containers
docker-compose down
```

### Without Docker

```bash
# Start development server
php artisan serve --host=0.0.0.0

# Run migrations (central)
php artisan migrate --path=database/migrations/central

# Run migrations (tenants)
php artisan tenants:migrate

# Create tenant
php artisan tenants:create <name> <domain>

# Seed
php artisan db:seed
php artisan tenants:seed

# Run tests (when implemented)
php artisan test

# Interactive shell (REPL)
php artisan tinker

# Clear caches
php artisan optimize:clear

# View routes
php artisan route:list
```

## Requirements

### Docker Setup
- Docker 20.10+
- Docker Compose 3.8+

### Local Development
- PHP 8.2+
- PostgreSQL 15+ with PostGIS extension
- Redis (for cache and queues)
- Composer 2+


## License

Proprietary - All rights reserved.
