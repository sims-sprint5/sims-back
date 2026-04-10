# Frontend API – Reservas y Mapa (Tenant)

Este documento resume los cambios de backend para el flujo de reservas, visibilidad de vehículos en mapa y renovación.

## 1) Base de API (tenant)

Todas estas rutas viven bajo dominio tenant y prefijo:

- `/api/v1/...`

Ejemplo:

- `https://{tenant}.{base-domain}/api/v1/vehicles`

Autenticación requerida en todas las rutas de este documento:

- Header: `Authorization: Bearer {token}`

---

## 2) Reglas funcionales implementadas

### 2.1 Visibilidad de vehículos en mapa

`GET /api/v1/vehicles`

- **Admin**: ve todos los vehículos.
- **Usuario normal**:
  - ve vehículos `available`.
  - ve también el vehículo que **él mismo** tiene reservado (`pending` o `active`, no vencido).
  - **ve también vehículos con reservas FUTURAS** de otros usuarios (porque el vehículo está disponible ahora).
  - **no ve** vehículos con reservas ACTIVAS (ya iniciadas) de otros usuarios.

`GET /api/v1/vehicles/{id}`

- Si usuario normal intenta abrir un vehículo con reserva ACTIVA de otro usuario, responde `404 Vehicle not available.`

### 2.1.1 Campos adicionales en respuesta de vehículos

Cada vehículo puede incluir (si tiene próxima reserva):

```json
{
  "vehicle_id": 1,
  "status": "available",
  "next_reservation": {
    "start_date": "2026-04-30T10:00:00Z",
    "end_date": "2026-04-30T18:00:00Z",
    "user_name": "Juan García"
  }
}
```

Esto permite mostrar en el mapa cuándo estará disponible cada vehículo.

### 2.2 Reserva y bloqueo de vehículo

`POST /api/v1/reservations`

**Validación de disponibilidad por período:**

- Si existe una reserva que SOLAPA con el período solicitado: `409 Conflict`
- Backend retorna cuándo estará disponible:
  ```json
  {
    "message": "Vehicle is not available for the requested period. It will be available from 2026-04-10 18:00:00.",
    "available_at": "2026-04-10T18:00:00Z",
    "conflicting_reservation": {
      "start_date": "2026-04-08T10:00:00Z",
      "end_date": "2026-04-10T18:00:00Z"
    }
  }
  ```

**Cambio de estado del vehículo:**

- Al crear una reserva válida, el vehículo se mantiene `available` (porque aún no ha comenzado).
- Cuando llega la fecha/hora de `start_date`, el vehículo cambia automáticamente a `reserved`.
- Cuando llega `end_date`, vuelve a `available`.

### 2.2.1 Verificar disponibilidad antes de reservar

`GET /api/v1/reservations/check-availability`

Query params:

- `vehicle_id` (requerido)
- `start_date` (requerido, formato ISO 8601)
- `end_date` (requerido, formato ISO 8601)

Respuesta:

```json
{
  "available": true
}
```

O en caso de conflicto:

```json
{
  "available": false,
  "message": "Vehicle is not available...",
  "available_at": "2026-04-10T18:00:00Z",
  "conflicting_reservation": {...}
}
```

**Recomendación UI:** Llamar a este endpoint cuando el usuario seleccione fechas, para mostrar disponibilidad antes de enviar la reserva.

### 2.3 Transiciones automáticas de estado

El backend maneja automáticamente estas transiciones:

**Cuando start_date <= now:**
- Reserva pasa a estado `active` (si era `pending`)
- Vehículo pasa a estado `reserved`

**Cuando end_date <= now:**
- Reserva pasa a estado `completed` (si era `pending` o `active`)
- Vehículo vuelve a estado `available` (si no hay otras reservas activas)

Esta limpieza se ejecuta automáticamente en endpoints de vehículos y reservas cada vez que se accede (`releaseExpiredReservations()`).

### 2.4 Renovación (sin pasarela implementada aún)

Se expone intención de renovación:

- `POST /api/v1/reservations/{id}/renewal-intent`

Respuesta incluye URL placeholder de pago:

- `payment_url: /payments/reservations/{id}/renew`

---

## 3) Rutas de vehículos (frontend mapa)

## `GET /api/v1/vehicles`

Query params:

- `per_page` (opcional, default 15)

Uso frontend:

- Esta es la ruta principal del mapa.
- Ya devuelve lista filtrada por rol/usuario según reglas de visibilidad.

## `GET /api/v1/vehicles/{id}`

Uso frontend:

- Ficha/detalle de vehículo.
- En usuario normal puede dar `404` si el vehículo no le corresponde visualizar.

---

## 4) Rutas de reservas

## `GET /api/v1/reservations`

- Admin: ve todas las reservas.
- Usuario: solo ve sus reservas.

## `GET /api/v1/reservations/{id}`

- Admin: acceso total.
- Usuario: solo su propia reserva.

## `POST /api/v1/reservations`

Body:

```json
{
  "vehicle_id": 12,
  "start_date": "2026-04-08T10:00:00Z",
  "end_date": "2026-04-08T11:30:00Z",
  "pickup_location": "Punto A",
  "dropoff_location": "Punto B"
}
```

Notas:

- Usuario normal no puede forzar `user_id` ni `status`.
- Admin puede crear para otro usuario usando `user_id`.

## `PUT/PATCH /api/v1/reservations/{id}`

- Mantiene reglas de permisos.
- Si cambia a un vehículo ocupado por otra reserva activa: `409`.

## `DELETE /api/v1/reservations/{id}`

- Al borrar, el backend reevalúa disponibilidad y puede devolver el vehículo a `available`.

## `GET /api/v1/reservations/user/{userId}`

- Usuario normal solo su propio `userId`.
- Admin puede consultar cualquiera.

## `GET /api/v1/reservations/check-availability`

Verifica disponibilidad de un vehículo para un período específico SIN crear reserva.

Query params:

```
GET /api/v1/reservations/check-availability?vehicle_id=12&start_date=2026-04-30T10:00:00Z&end_date=2026-04-30T18:00:00Z
```

Respuesta:

- `{"available": true}` si no hay conflictos
- `{"available": false, "message": "...", "available_at": "...", ...}` si hay conflicto

## `PATCH /api/v1/reservations/{id}/status` (Admin)

- Estados permitidos: `pending | active | completed | cancelled`.

## `POST /api/v1/reservations/{id}/renewal-intent`

Uso frontend:

- Botón “Ampliar tiempo”.
- Backend devuelve `payment_url` placeholder para redirigir cuando exista pasarela real.

---

## 5) Campos nuevos para frontend (en respuestas de reserva)

En listados y detalle de reservas ahora llegan estos campos calculados:

- `is_expired` (boolean)
- `minutes_remaining` (integer, mínimo 0)
- `can_renew` (boolean)
- `renewal_payment_url` (string|null)
- `renewal_notice` (string|null)

### Recomendación UI

- Mostrar aviso cuando `renewal_notice !== null`.
- Mostrar botón “Ampliar tiempo” cuando `can_renew === true`.
- Al pulsar:
  1. llamar `POST /api/v1/reservations/{id}/renewal-intent`
  2. redirigir a `payment_url` (placeholder por ahora).

---

## 6) Códigos HTTP esperados (resumen)

- `200/201`: OK
- `401`: no autenticado
- `403`: sin permisos
- `404`: recurso no visible/no encontrado (ej. vehículo reservado por otro usuario)
- `409`: conflicto de reserva (vehículo ya reservado)
- `422`: validación

---

## 7) Checklist frontend sugerido

### Para el mapa de vehículos

- Mapa consume `GET /api/v1/vehicles` (ya filtrado por backend).
- Mostrar cada vehículo con su estado (available/reserved).
- Si vehículo tiene `next_reservation` mostrar tooltip/popup con próxima reserva y usuario.
- Permitir click para ver detalle en `GET /api/v1/vehicles/{id}`.

### Para crear/editar reservas

- **Antes de permitir reservar:**
  1. Usuario selecciona fechas/horas (start_date, end_date).
  2. Llamar a `GET /api/v1/reservations/check-availability` con esos parámetros.
  3. Si `available: false`, mostrar mensaje con `available_at` ("Disponible desde...").
  4. Si `available: true`, permitir crear reserva con `POST /api/v1/reservations`.

- **Body de POST:**
  ```json
  {
    "vehicle_id": 12,
    "start_date": "2026-04-30T10:00:00Z",
    "end_date": "2026-04-30T18:00:00Z",
    "pickup_location": "Punto A",
    "dropoff_location": "Punto B"
  }
  ```

### En listado de reservas

- Renderizar `minutes_remaining`, `renewal_notice`, `can_renew`.
- Mostrar aviso cuando `renewal_notice !== null` ("Tu reserva está por finalizar").
- Implementar botón "Ampliar tiempo" cuando `can_renew === true`.
- Al pulsar:
  1. Llamar `POST /api/v1/reservations/{id}/renewal-intent`.
  2. Redirigir a `payment_url` (placeholder por ahora).

### Estados esperados en la UI

- **Reserva futura (pending, start_date > now):**
  - Vehículo aparece `available` en mapa
  - Usuario verá su propia reserva en listado
  - `can_renew = false`

- **Reserva activa (active, start_date <= now < end_date):**
  - Vehículo aparece `reserved` en mapa
  - Otros usuarios NO verán este vehículo
  - `minutes_remaining` cuenta atrás
  - `can_renew = true` (si faltan < 15 min por defecto)

- **Reserva completada (completed, end_date <= now):**
  - Vehículo vuelve a `available` en mapa
  - `can_renew = false`
