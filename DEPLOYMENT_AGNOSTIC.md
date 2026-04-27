# Deployment Agnóstico de Servidor - Guía de Migración

Este documento resume los cambios realizados para que el código sea portátil a cualquier servidor, no solo Digital Ocean.

## ✅ Cambios Realizados

### 1. Configuración de Docker Compose
**Archivo:** `docker-compose.yml`

- ❌ **Removido:** IPs hardcodeadas de Digital Ocean (`178.62.229.151`)
- ✅ **Actualizado:** Valores por defecto a localhost para desarrollo
- ✅ **Resultado:** Todos los valores están configurables mediante `.env`

**Ejemplos de variables ahora configurables:**
```env
APP_URL=${APP_URL:-http://localhost:8000}
TENANT_BASE_DOMAIN=${TENANT_BASE_DOMAIN:-lvh.me}
SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS:-localhost,localhost:8000,localhost:5173,lvh.me,.lvh.me}
```

### 2. Archivo de Ejemplo de Entorno
**Archivo:** `.env.example`

- ❌ **Removido:** Sección específica "Producción DigitalOcean"
- ✅ **Agregado:** Sección "Producción Genérica" con placeholders
- ✅ **Resultado:** Documentación clara para cualquier servidor

```env
# --- Producción Genérica (Reemplazar YOUR_DOMAIN con tu dominio) ---
# APP_URL=https://YOUR_DOMAIN
# TENANT_BASE_DOMAIN=YOUR_DOMAIN
```

### 3. Workflow de GitHub Actions
**Archivo:** `.github/workflows/deploy-main.yml`

- ❌ **Removido:** Nombre "Deploy Main to DigitalOcean"
- ❌ **Removido:** IPs hardcodeadas de DO en CORS y SANCTUM
- ✅ **Actualizado:** Nombre a "Deploy Main to Production Server"
- ✅ **Resultado:** Workflow genérico que funciona en cualquier servidor

### 4. Documentación de Deployment
**Archivo:** `README.md`

- ❌ **Removido:** Referencias específicas a DigitalOcean con IP pública
- ✅ **Actualizado:** Sección "Despliegue en Producción (Cualquier Servidor)"
- ✅ **Agregado:** Instrucciones genéricas

### 5. Valores Default en Código
**Archivos:**
- `app/Console/Commands/GenerateTenantCertificates.php`
- `scripts/namecom_certbot_dns01.py`

- ❌ **Removido:** Email hardcodeado `admin@simsgrup2.app`
- ❌ **Removido:** Dominio hardcodeado `simsgrup2.app`
- ✅ **Actualizado:** Valores requieren configuración explícita en `.env`

### 6. Documentación de Debug
**Archivo:** `DEBUG_SERVER.md`

- ❌ **Removido:** Referencia específica a servidor de prueba
- ✅ **Actualizado:** Instrucciones genéricas con placeholder `YOUR_SERVER_IP`

## 📋 Guía de Deployment en Nuevo Servidor

### Paso 1: Preparar el .env

```bash
# Copia el archivo de ejemplo
cp .env.example .env

# Edita los valores kritischen:
nano .env
```

**Valores esenciales a configurar:**
```env
# Base de datos
DB_HOST=tu-servidor-db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña

# Dominios
APP_URL=https://tu-dominio.com
APP_DOMAIN=tu-dominio.com
TENANT_BASE_DOMAIN=tu-dominio.com
FRONTEND_URL=https://frontend.tu-dominio.com

# Certificados SSL (si usas certbot)
CERT_EMAIL=admin@tu-dominio.com

# Services (Stripe, etc.)
STRIPE_KEY=pk_live_tu_key
STRIPE_SECRET=sk_live_tu_secret
```

### Paso 2: Build y Deploy

```bash
# Generar APP_KEY
docker compose run --rm api php artisan key:generate --ansi

# Iniciar servicios
docker compose up -d --build

# Ejecutar migraciones
docker compose exec api php artisan migrate --force

# Seed de datos
docker compose exec api php artisan db:seed --force
docker compose exec api php artisan tenants:migrate --force
```

### Paso 3: Verificar Deployment

```bash
# Verificar contenedores
docker compose ps

# Ver logs
docker compose logs -f api

# Verificar que la API responde
docker compose exec api php artisan about
```

## 🔄 Configuración por Proveedor de Servidor

### AWS (EC2, Lightsail)
No requiere cambios especiales. Usa:
- `APP_URL`: Tu dominio o IP elástica
- `DB_HOST`: RDS endpoint o IP local
- Para S3: Configura `AWS_*` variables

### DigitalOcean
Mismo procedimiento que any other server. Usa:
- `APP_URL`: IP del Droplet o dominio
- `DB_HOST`: localhost (si está en mismo Droplet) o IP de managed DB
- Documentación específica: Ver `SETUP_PRODUCTION.md` (archivado)

### Linode, Vultr, Hetzner, etc.
El procedimiento es idéntico. Solo cambian:
- La forma de acceder (SSH key management)
- El proveedor de DNS (para certificados SSL)
- La IP o dominio asignado

### Kubernetes (Helm, Docker Swarm)
El `docker-compose.yml` puede:
- Usarse directamente en Docker Swarm
- Convertirse a Helm charts para Kubernetes
- Las variables de entorno se aplican igual

## 🔐 Variables de Entorno Críticas

| Variable | Descripción | Default | Requerida |
|----------|-------------|---------|-----------|
| `APP_ENV` | ambiente (production, local, testing) | local | Sí |
| `APP_URL` | URL base de la aplicación | http://localhost | Sí |
| `DB_HOST` | Host de PostgreSQL | postgres | Sí |
| `DB_USERNAME` | Usuario de PostgreSQL | sims_user | Sí |
| `DB_PASSWORD` | Contraseña de PostgreSQL | sims_password | Sí |
| `TENANT_BASE_DOMAIN` | Dominio base para tenants | lvh.me | Sí |
| `CERT_EMAIL` | Email para certificados SSL | (vacío) | Para SSL |
| `API_TOKEN` | Token de API de DNS provider | (vacío) | Para DNS-01 |

## 📝 Documentación Específica de Digital Ocean

La documentación histórica específica para Digital Ocean se encuentra en:
- `SETUP_PRODUCTION.md` - Instrucciones detalladas de setup en DO Droplet

Esta documentación es útil como referencia pero ahora cualquier servidor Linux con Docker puede usar el mismo procedimiento.

## ⚠️ Consideraciones Importantes

1. **SSL/TLS:** El código soporta certificados Let's Encrypt con certbot
2. **DNS DNS-01:** El script `scripts/namecom_certbot_dns01.py` es específico para name.com. Para otros DNS providers, actualiza el script
3. **Base de Datos:** PostgreSQL es requerido. Soporta:
   - PostgreSQL local en el mismo Droplet
   - PostgreSQL managed (DO Database, RDS, etc.)
4. **Almacenamiento:** Por defecto usa filesystem local. Soporta also S3/compatible

## 🚀 Deploy Automático con GitHub Actions

El workflow `.github/workflows/deploy-main.yml` es agnóstico del servidor:
- Se conecta via SSH usando secrets
- Requiere configurar en GitHub:
  - `DEPLOY_SSH_KEY_B64`: Tu SSH private key en base64
  - `DEPLOY_HOST`: IP o dominio del servidor
  - `DEPLOY_USER`: Usuario SSH (típicamente `root`)

```bash
# Codificar tu SSH key para GitHub
cat ~/.ssh/deploy_key | base64 -w 0 | pbcopy
```

---

**Última actualización:** Abril 2026  
**Estado:** ✅ Listo para cualquier servidor Linux con Docker
