# 🚗 Blink - Sistema de Gestión de Vehículos Compartidos


## 🛠️ Stack Tecnológico

| Tecnología | Versión | Propósito |
|-----------|---------|-----------|
| **PHP** | ^8.2 | Lenguaje de programación |
| **Laravel** | ^12.0 | Framework backend |
| **Laravel Sanctum** | ^4.0 | Autenticación API (Token-based) |
| **MariaDB** | 11.2 | Base de datos relacional |
| **Docker** | Latest | Contenedorización |
| **Composer** | Latest | Gestión de dependencias PHP |
| **PHPUnit** | ^11.5 | Testing unitario |

---

## 📁 Estructura del Proyecto

```
Laravel_Sprint4_Equip3/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/              # Controladores de la API
│   │           ├── AuthController.php
│   │           ├── UserController.php
│   │           ├── VehicleController.php
│   │           ├── ReservationController.php
│   │           ├── TicketController.php
│   │           └── GeofenceController.php
│   ├── Models/                   # Modelos Eloquent ORM
│   │   ├── User.php
│   │   ├── Vehicle.php
│   │   ├── Reservation.php
│   │   ├── Ticket.php
│   │   ├── Geofence.php
│   │   └── VehicleGeofenceLog.php
│   └── Providers/
│
├── database/
│   ├── migrations/               # Migraciones de base de datos
│   ├── seeders/                  # Datos de prueba
│   └── factories/
│
├── routes/
│   ├── api.php                   # Rutas de la API
│   └── web.php
│
├── tests/                        # Tests automatizados
│   ├── Feature/
│   └── Unit/
│
├── config/                       # Archivos de configuración
├── docker-compose.yml            # Orquestación de contenedores
├── DockerFile                    # Imagen Docker de la aplicación
└── .env.example                  # Plantilla de variables de entorno
```

### 🎯 Explicación de componentes clave:

- **Controllers/Api**: Lógica de negocio y gestión de endpoints
- **Models**: Representación de tablas de base de datos (ORM)
- **Migrations**: Control de versiones del esquema de base de datos
- **Seeders**: Datos iniciales para desarrollo y testing
- **Routes/api.php**: Definición de todos los endpoints REST

---

## ✅ Requisitos Previos

Antes de comenzar, asegúrate de tener instalado:

- **Docker** >= 20.10
- **Docker Compose** >= 2.0
- **Git**
- **(Opcional)** PHP >= 8.2 y Composer (para desarrollo sin Docker)

---

## 🚀 Instalación y Configuración

### 1️⃣ Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd Laravel_Sprint4_Equip3
```

### 2️⃣ Configurar variables de entorno

```bash
cp .env.example .env
```

Edita el archivo `.env` con tus configuraciones (ver sección [Variables de Entorno](#-variables-de-entorno)).

### 3️⃣ Iniciar contenedores Docker

```bash
docker-compose up -d
```

Esto levantará dos servicios:
- **app**: Aplicación Laravel (puerto 8001)
- **db**: Base de datos MariaDB (puerto 3306)

### 4️⃣ Instalar dependencias

```bash
docker exec -it laravel_app composer install
```

### 5️⃣ Generar key de la aplicación

```bash
docker exec -it laravel_app php artisan key:generate
```

### 6️⃣ Ejecutar migraciones

```bash
docker exec -it laravel_app php artisan migrate
```

### 7️⃣ (Opcional) Poblar base de datos con datos de prueba

```bash
docker exec -it laravel_app php artisan db:seed
```

---

## ⚙️ Variables de Entorno

Ejemplo de configuración en el archivo `.env`:

```env
# Configuración de la aplicación
APP_NAME=Blink
APP_ENV=local
APP_KEY=base64:GENERATED_KEY
APP_DEBUG=true
APP_URL=http://localhost:8001

# Base de datos
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=blink_sprint4_equip3
DB_USERNAME=root
DB_PASSWORD=password

# Sanctum (autenticación)
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1

# Cache y sesiones
CACHE_STORE=database
SESSION_DRIVER=database
```

---

## ▶️ Ejecución del Proyecto

### Modo desarrollo con Docker

```bash
# Iniciar servicios
docker-compose up -d

# Ver logs en tiempo real
docker-compose logs -f app
```

### Modo desarrollo sin Docker

```bash
# Instalar dependencias
composer install

# Configurar base de datos local en .env

# Ejecutar migraciones
php artisan migrate

# Iniciar servidor
php artisan serve
```

La API estará disponible en: `http://localhost:8001`

### 🧪 Ejecutar tests

```bash
docker exec -it laravel_app php artisan test
```

---

## 🌐 Endpoints Principales

### 🔐 Autenticación

| Método | Endpoint | Descripción | Auth |
|--------|----------|-------------|------|
| POST | `/api/v1/auth/register` | Registrar nuevo usuario | No |
| POST | `/api/v1/auth/login` | Iniciar sesión | No |
| POST | `/api/v1/auth/logout` | Cerrar sesión | Sí |
| GET | `/api/v1/auth/me` | Obtener usuario autenticado | Sí |
| POST | `/api/v1/auth/change-password` | Cambiar contraseña | Sí |

**Ejemplo de registro:**
```json
POST /api/v1/auth/register
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "612345678",
  "role": "user"
}
```

### 👥 Usuarios

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/users` | Listar todos los usuarios |
| GET | `/api/v1/users/{id}` | Obtener usuario específico |
| POST | `/api/v1/users` | Crear usuario |
| PUT | `/api/v1/users/{id}` | Actualizar usuario |
| DELETE | `/api/v1/users/{id}` | Eliminar usuario |

### 🚙 Vehículos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/vehicles` | Listar vehículos |
| GET | `/api/v1/vehicles/{id}` | Obtener vehículo |
| POST | `/api/v1/vehicles` | Crear vehículo |
| PUT | `/api/v1/vehicles/{id}` | Actualizar vehículo |
| DELETE | `/api/v1/vehicles/{id}` | Eliminar vehículo |
| GET | `/api/v1/vehicles/{id}/reservations` | Reservas de un vehículo |
| PATCH | `/api/v1/vehicles/{id}/location` | Actualizar ubicación GPS |

**Ejemplo de actualización de ubicación:**
```json
PATCH /api/v1/vehicles/1/location
{
  "latitude": 41.3851,
  "longitude": 2.1734
}
```

### 📅 Reservas

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/reservations` | Listar reservas |
| GET | `/api/v1/reservations/{id}` | Obtener reserva |
| POST | `/api/v1/reservations` | Crear reserva |
| PUT | `/api/v1/reservations/{id}` | Actualizar reserva |
| DELETE | `/api/v1/reservations/{id}` | Cancelar reserva |
| GET | `/api/v1/reservations/user/{userId}` | Reservas de un usuario |
| PATCH | `/api/v1/reservations/{id}/status` | Cambiar estado |

### 🎫 Tickets de Soporte

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/tickets` | Listar tickets |
| GET | `/api/v1/tickets/{id}` | Obtener ticket |
| POST | `/api/v1/tickets` | Crear ticket |
| PUT | `/api/v1/tickets/{id}` | Actualizar ticket |
| DELETE | `/api/v1/tickets/{id}` | Eliminar ticket |
| PATCH | `/api/v1/tickets/{id}/assign` | Asignar ticket |
| PATCH | `/api/v1/tickets/{id}/status` | Cambiar estado |

### 🗺️ Geofencing

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/geofences` | Listar geofences |
| GET | `/api/v1/geofences/{id}` | Obtener geofence |
| POST | `/api/v1/geofences` | Crear geofence |
| PUT | `/api/v1/geofences/{id}` | Actualizar geofence |
| DELETE | `/api/v1/geofences/{id}` | Eliminar geofence |
| GET | `/api/v1/geofences/{id}/logs` | Historial de logs |
| POST | `/api/v1/geofences/check-vehicle` | Verificar vehículo en geofence |

### 🔑 Autenticación de Requests

Todas las rutas protegidas requieren el header:

```http
Authorization: Bearer {tu_token_aqui}
```

El token se obtiene al hacer login exitoso.

---

## 🗄️ Gestión de Base de Datos

### Crear una nueva migración

```bash
docker exec -it laravel_app php artisan make:migration create_nombre_tabla
```

### Ejecutar migraciones

```bash
docker exec -it laravel_app php artisan migrate
```

### Rollback de migraciones

```bash
docker exec -it laravel_app php artisan migrate:rollback
```

### Refrescar base de datos (⚠️ elimina todos los datos)

```bash
docker exec -it laravel_app php artisan migrate:fresh --seed
```

### Crear un seeder

```bash
docker exec -it laravel_app php artisan make:seeder NombreSeeder
```

---
