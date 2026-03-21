# Setup Producción - SIMS Back

Documentación para recuperar el ambiente de producción desde cero si algo falla.

---

## 1. Generar SSH Key para Deploy (LOCAL - Windows/Git Bash)

```bash
# Abre Git Bash
ssh-keygen -t ed25519 -f ~/.ssh/deploy_key

# Ver clave privada (para GitHub secret)
cat ~/.ssh/deploy_key | base64 -w 0

# Ver clave pública (para DigitalOcean)
cat ~/.ssh/deploy_key.pub
```

**Guardar en GitHub:**
- Settings → Secrets and variables → Actions
- New repository secret
  - Name: `DEPLOY_SSH_KEY_B64`
  - Value: Salida del `base64 -w 0` anterior

**Guardar en DigitalOcean:**
- Conéctate por consola web o SSH existente
- Agrega a `~/.ssh/authorized_keys`:
  ```bash
  echo "CONTENIDO_DE_deploy_key.pub" >> ~/.ssh/authorized_keys
  chmod 600 ~/.ssh/authorized_keys
  ```

---

## 2. Configurar Secrets en GitHub

Settings → Secrets and variables → Actions → New repository secret

| Name | Value |
|---|---|
| `DEPLOY_SSH_KEY_B64` | Output de `cat ~/.ssh/deploy_key \| base64 -w 0` |
| `DEPLOY_HOST` | `YOUR_SERVER_IP` |
| `DEPLOY_USER` | `root` |

---

## 3. DigitalOcean - Archivo .env

```bash
ssh -i ~/.ssh/deploy_key root@YOUR_SERVER_IP
nano /var/www/sims-back/.env
```

**Contenido `.env` mínimo:**

```env
SUPERADMIN_NAME="Super Admin"
SUPERADMIN_EMAIL=superadmin@example.com
SUPERADMIN_PASSWORD=change_this_superadmin_password

PGADMIN_EMAIL=pgadmin@example.com
PGADMIN_PASSWORD=change_this_pgadmin_password

FRONTEND_URL=http://YOUR_SERVER_IP

APP_NAME=Sims
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://YOUR_SERVER_IP
APP_DOMAIN=YOUR_SERVER_IP
TENANT_BASE_DOMAIN=YOUR_DOMAIN.app
TENANT_DEFAULT_ADMIN_PASSWORD=change_this_tenant_password

CERT_EMAIL=admin@YOUR_DOMAIN.app
SSL_DNS_SCRIPT_PATH=scripts/namecom_certbot_dns01.py
API_USER=your_namecom_api_user
API_TOKEN=your_namecom_api_token
CERTBOT_BIN=certbot
CERTBOT_CONFIG_DIR=/etc/letsencrypt
CERTBOT_WORK_DIR=/var/lib/letsencrypt
CERTBOT_LOGS_DIR=/var/log/letsencrypt
DNS_PROPAGATION_TIMEOUT=180
DNS_PROPAGATION_INTERVAL=15
DNS_PROPAGATION_INITIAL_WAIT=20
DNS_TTL=60

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=sims
DB_USERNAME=sims_user
DB_PASSWORD=sims_password

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.YOUR_DOMAIN.app
SANCTUM_STATEFUL_DOMAINS=YOUR_SERVER_IP,YOUR_SERVER_IP:8000,YOUR_DOMAIN.app,*.YOUR_DOMAIN.app

CORS_ALLOWED_ORIGINS=http://YOUR_SERVER_IP,http://YOUR_SERVER_IP:8000
CORS_ALLOWED_ORIGINS_PATTERNS="#^https?://[a-z0-9-]+[.]YOUR_DOMAIN[.]app(:[0-9]+)?$#i"

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
```

---

## 4. GitHub Actions Workflow

Archivo `.github/workflows/deploy-main.yml` está configurado para:
1. ✅ Ejecutar tests (pint, migrations, phpunit)
2. ✅ Si pasan, hacer deploy a DigitalOcean
3. ✅ Desplegar código
4. ✅ Ejecutar migraciones
5. ✅ Ejecutar seeders (crea SuperAdmin)

**Solo necesitas hacer push a `main` y automáticamente se deploya.**

> Nota: para SSL dinámico por tenant con dns-01, asegúrate de tener `API_USER`, `API_TOKEN` y `CERT_EMAIL` configurados en el `.env` de producción.

---

## 5. Primeros Comandos en DigitalOcean

```bash
ssh -i ~/.ssh/deploy_key root@YOUR_SERVER_IP

# Ir al proyecto
cd /var/www/sims-back

# Ver estado de contenedores
docker-compose ps

# Ver logs de la API
docker-compose logs -f api

# Ejecutar comando en contenedor
docker-compose exec api php artisan command
```

---

## 6. Crear SuperAdmin Inicial

```bash
ssh -i ~/.ssh/deploy_key root@YOUR_SERVER_IP
cd /var/www/sims-back

# Se crea automáticamente al deplegar si está configurado en .env
# Pero si necesitas recrear:
docker-compose exec api php artisan db:seed --class=SuperAdminSeeder --force
```

**Credenciales SuperAdmin:**
- Email: `superadmin@example.com` (del `.env`)
- Password: `change_this_superadmin_password` (del `.env`)

---

## 7. Crear Tenant Manualmente

```bash
# 1. Obtén token SuperAdmin
curl -X POST http://YOUR_SERVER_IP:8000/api/v1/superadmin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@example.com","password":"change_this_superadmin_password"}'

# Guardar el token de la respuesta como $TOKEN

# 2. Crear tenant
curl -X POST http://YOUR_SERVER_IP:8000/api/v1/superadmin/tenants \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "empresa1",
    "name": "Empresa Uno",
    "admin_email": "admin@empresa1.YOUR_DOMAIN.app",
    "admin_password": "admin1234"
  }'

# 3. Respuesta incluye URL de acceso:
# http://empresa1.YOUR_DOMAIN.app/api/v1/auth/login
```

---

## 8. Seeding Manual de Tenant

```bash
ssh -i ~/.ssh/deploy_key root@YOUR_SERVER_IP
cd /var/www/sims-back

# Si no se seedeó automáticamente
docker-compose exec api php artisan tenants:seed --tenants=empresa1
```

---

## 9. Acceder a PgAdmin

**URL:** `http://YOUR_SERVER_IP:5050`

**Credenciales:**
- Email: `pgadmin@example.com`
- Password: `change_this_pgadmin_password`

**Registrar servidor PostgreSQL:**
1. Right-click "Servers" → Register → Server
2. **General tab:**
   - Name: `sims-postgres`
3. **Connection tab:**
   - Host: `postgres`
   - Port: `5432`
   - Database: `sims`
   - Username: `sims_user`
   - Password: `sims_password`
4. Save

---

## 10. Configurar DNS para Tenants

**En tu registrador (YOUR_DOMAIN.app):**

Crea un registro DNS wildcard:
```
Name: *.
Type: A
Value: YOUR_SERVER_IP
```

O específico por tenant:
```
Name: empresa1
Type: A
Value: YOUR_SERVER_IP
```

---

## 11. SSL Certificates para Tenants (Automático DNS-01)

```bash
ssh -i ~/.ssh/deploy_key root@YOUR_SERVER_IP

# Entrar al proyecto
cd /var/www/sims-back

# Probar generación manual con el script (si quieres validar)
docker-compose exec api python3 scripts/namecom_certbot_dns01.py \
  --mode issue \
  --domain YOUR_DOMAIN.app \
  --subdomain empresa1
```

### Flujo automático al crear tenant
- Al crear tenant (`POST /api/v1/superadmin/tenants`), el backend intenta emitir SSL para `tenant.YOUR_DOMAIN.app`.
- El script crea el TXT `_acme-challenge`, espera propagación DNS y ejecuta certbot con `dns-01`.
- Al finalizar, elimina el TXT para mantener el DNS limpio.

### Requisitos para que funcione
- Dominio gestionado en Name.com con API habilitada.
- `API_USER` y `API_TOKEN` válidos en `.env`.
- `CERT_EMAIL` definido.
- DNS wildcard o registros A apuntando al servidor para los subdominios tenant.

---

## 12. Logs y Debuggeo

```bash
ssh -i ~/.ssh/deploy_key root@YOUR_SERVER_IP
cd /var/www/sims-back

# Logs de Docker (tiempo real)
docker-compose logs -f api

# Logs de Laravel
docker-compose exec api tail -f storage/logs/laravel.log

# Buscar errores específicos
docker-compose logs api | grep -i "error\|seed\|exception"

# Limpiar caché
docker-compose exec api php artisan cache:clear
docker-compose exec api php artisan config:clear
```

---

## 13. Problemas Comunes

### Error: `KeyError: 'ContainerConfig'`
```bash
# Limpiar Docker sin perder datos
docker-compose down
docker system prune -f --all
docker-compose up -d
```

### Seeder no se ejecuta
```bash
# Ejecutar manualmente
docker-compose exec api php artisan tenants:seed --tenants=empresa1
```

### Base de datos vacía después de crear tenant
- Espera 10 segundos después de crear el tenant
- El seeding se ejecuta automáticamente
- Si no, ejecuta comando anterior

### SSL no se genera al crear tenant
```bash
# Ver logs de la API y buscar salida del script DNS-01
docker-compose logs api | grep -i "ssl\|certbot\|dns\|acme\|name.com"

# Probar script manualmente dentro del contenedor API
docker-compose exec api python3 scripts/namecom_certbot_dns01.py \
  --mode issue \
  --domain YOUR_DOMAIN.app \
  --subdomain empresa1
```

Si falla, revisa primero `API_USER`, `API_TOKEN`, `CERT_EMAIL` y que el dominio esté en Name.com.

### Migrations no se ejecutan
```bash
docker-compose exec api php artisan migrate --force
```

---

## 14. Hacer Deploy (automático)

**Solo necesitas:**
```bash
git add .
git commit -m "Tu mensaje"
git push origin main
```

El workflow hace todo automáticamente:
1. ✅ Tests pass (pint, phpunit, migrations)
2. ✅ Build Docker image
3. ✅ Deploy a DigitalOcean
4. ✅ Restart containers
5. ✅ Run migrations
6. ✅ Run seeders

**Ver estado:** GitHub → Actions → último workflow

---

## 15. Restaurar desde Backup

Si necesitas restaurar la BD completa:

```bash
ssh -i ~/.ssh/deploy_key root@YOUR_SERVER_IP
cd /var/www/sims-back

# Hacer backup
docker-compose exec postgres pg_dump -U sims_user sims > backup.sql

# Restaurar
docker-compose exec -T postgres psql -U sims_user sims < backup.sql
```

---

## Resumen Rapido

| Acción | Comando |
|--------|---------|
| SSH DigitalOcean | `ssh -i ~/.ssh/deploy_key root@YOUR_SERVER_IP` |
| Ver logs | `docker-compose logs -f api` |
| Crear tenant | POST `/api/v1/superadmin/tenants` con token |
| Seed tenant | `docker-compose exec api php artisan tenants:seed --tenants=empresa1` |
| Acceder PgAdmin | `http://YOUR_SERVER_IP:5050` |
| Deploy | `git push origin main` (automático) |

---

**Última actualización:** 2026-03-17
