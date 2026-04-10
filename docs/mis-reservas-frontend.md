# Frontend - Página "Mis Reservas"

Especificación para implementar la página de gestión de reservas del usuario final.

---

## 1. Endpoints necesarios

### Obtener todas mis reservas

```
GET /api/v1/reservations
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query params (opcionales):**
- `per_page`: límite de resultados (default 15)

**Respuesta (200):**
```json
{
  "data": [
    {
      "reservation_id": 1,
      "user_id": 5,
      "vehicle_id": 12,
      "start_date": "2026-04-30T10:00:00.000000Z",
      "end_date": "2026-04-30T18:00:00.000000Z",
      "pickup_location": "Punto A",
      "dropoff_location": "Punto B",
      "status": "pending",
      "total_cost": "45.50",
      "created_at": "2026-04-08T12:00:00.000000Z",
      "updated_at": "2026-04-08T12:00:00.000000Z",
      "user": {
        "user_id": 5,
        "name": "Juan García",
        "email": "juan@example.com"
      },
      "vehicle": {
        "vehicle_id": 12,
        "license_plate": "AB-1234",
        "brand": "Toyota",
        "model": "Corolla",
        "status": "available"
      },
      "is_expired": false,
      "minutes_remaining": 1440,
      "can_renew": false,
      "renewal_payment_url": null,
      "renewal_notice": null
    },
    ...
  ],
  "links": {...},
  "meta": {
    "current_page": 1,
    "total": 5
  }
}
```

### Obtener detalle de una reserva

```
GET /api/v1/reservations/{reservation_id}
```

**Respuesta (200):**
```json
{
  "reservation_id": 1,
  "user_id": 5,
  "vehicle_id": 12,
  "start_date": "2026-04-30T10:00:00.000000Z",
  "end_date": "2026-04-30T18:00:00.000000Z",
  "pickup_location": "Punto A",
  "dropoff_location": "Punto B",
  "status": "pending",
  "total_cost": "45.50",
  "created_at": "2026-04-08T12:00:00.000000Z",
  "updated_at": "2026-04-08T12:00:00.000000Z",
  "user": {...},
  "vehicle": {...},
  "tickets": [],
  "is_expired": false,
  "minutes_remaining": 1440,
  "can_renew": false,
  "renewal_payment_url": null,
  "renewal_notice": null
}
```

---

## 2. Modificar una reserva

```
PATCH /api/v1/reservations/{reservation_id}
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (todos opcionales):**
```json
{
  "start_date": "2026-05-01T10:00:00Z",
  "end_date": "2026-05-01T18:00:00Z",
  "pickup_location": "Punto C",
  "dropoff_location": "Punto D"
}
```

**Respuestas:**

✅ **Exitoso (200):**
```json
{
  "reservation_id": 1,
  ...actualización...
}
```

❌ **Conflicto de disponibilidad (409):**
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

❌ **Validación fallida (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "start_date": ["The start date must be a valid date."]
  }
}
```

---

## 3. Cancelar una reserva

```
DELETE /api/v1/reservations/{reservation_id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta (200):**
```json
{
  "message": "Reservation deleted successfully"
}
```

---

## 4. Componente Vue - Estructura sugerida

```vue
<template>
  <div class="mis-reservas">
    <h1>Mis Reservas</h1>

    <!-- Loading -->
    <div v-if="loading" class="spinner">
      Cargando...
    </div>

    <!-- Error -->
    <div v-if="error" class="alert alert-danger">
      {{ error }}
    </div>

    <!-- Sin reservas -->
    <div v-if="!loading && reservas.length === 0" class="alert alert-info">
      No tienes reservas
    </div>

    <!-- Tabla/Lista de reservas -->
    <div v-if="!loading && reservas.length > 0">
      <table class="table">
        <thead>
          <tr>
            <th>Vehículo</th>
            <th>Matrícula</th>
            <th>Fecha Inicio</th>
            <th>Fecha Fin</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="reserva in reservas" :key="reserva.reservation_id">
            <td>
              {{ reserva.vehicle.brand }} {{ reserva.vehicle.model }}
            </td>
            <td>{{ reserva.vehicle.license_plate }}</td>
            <td>{{ formatDate(reserva.start_date) }}</td>
            <td>{{ formatDate(reserva.end_date) }}</td>
            <td>
              <span :class="`badge badge-${statusColor(reserva.status)}`">
                {{ reserva.status }}
              </span>
              <div v-if="reserva.renewal_notice" class="text-warning small">
                ⚠️ {{ reserva.renewal_notice }}
              </div>
            </td>
            <td>
              <!-- Botón editar (si reserva aún no ha empezado) -->
              <button
                v-if="!reserva.is_expired"
                @click="abrirEditar(reserva)"
                class="btn btn-sm btn-primary"
              >
                ✏️ Modificar
              </button>

              <!-- Botón renovar (si está activa y próxima a expirar) -->
              <button
                v-if="reserva.can_renew"
                @click="renovarReserva(reserva)"
                class="btn btn-sm btn-info"
              >
                ⏱️ Ampliar
              </button>

              <!-- Botón cancelar (si no ha expirado) -->
              <button
                v-if="!reserva.is_expired"
                @click="cancelarReserva(reserva)"
                class="btn btn-sm btn-danger"
              >
                ✕ Cancelar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal/Diálogo para editar -->
    <ReservaEditModal
      v-if="mostrarEditar"
      :reserva="reservaEditando"
      :ciudades="ciudades"
      @guardar="guardarEdicion"
      @cerrar="mostrarEditar = false"
    />
  </div>
</template>

<script>
import { defineComponent } from 'vue'
import api from '@/services/api.service'
import ReservaEditModal from '@/components/ReservaEditModal.vue'

export default defineComponent({
  components: {
    ReservaEditModal,
  },
  data() {
    return {
      reservas: [],
      loading: true,
      error: null,
      mostrarEditar: false,
      reservaEditando: null,
      ciudades: ['Punto A', 'Punto B', 'Punto C', 'Punto D'],
    }
  },
  created() {
    this.cargarReservas()
  },
  methods: {
    async cargarReservas() {
      try {
        this.loading = true
        const response = await api.get('/reservations')
        this.reservas = response.data.data || response.data
        this.error = null
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar reservas'
      } finally {
        this.loading = false
      }
    },

    abrirEditar(reserva) {
      this.reservaEditando = { ...reserva }
      this.mostrarEditar = true
    },

    async guardarEdicion(datosActualizados) {
      try {
        await api.patch(
          `/reservations/${this.reservaEditando.reservation_id}`,
          {
            start_date: datosActualizados.start_date,
            end_date: datosActualizados.end_date,
            pickup_location: datosActualizados.pickup_location,
            dropoff_location: datosActualizados.dropoff_location,
          }
        )
        this.mostrarEditar = false
        this.cargarReservas() // Refrescar lista
        this.$toast.success('Reserva actualizada')
      } catch (err) {
        const message = err.response?.data?.message || 'Error al actualizar'
        this.$toast.error(message)
      }
    },

    async cancelarReserva(reserva) {
      if (!confirm('¿Seguro que quieres cancelar esta reserva?')) {
        return
      }

      try {
        await api.delete(`/reservations/${reserva.reservation_id}`)
        this.cargarReservas() // Refrescar lista
        this.$toast.success('Reserva cancelada')
      } catch (err) {
        const message = err.response?.data?.message || 'Error al cancelar'
        this.$toast.error(message)
      }
    },

    async renovarReserva(reserva) {
      try {
        const response = await api.post(
          `/reservations/${reserva.reservation_id}/renewal-intent`
        )
        // Redirigir a pasarela de pago (por ahora solo placeholder)
        window.location.href = response.data.payment_url
      } catch (err) {
        const message = err.response?.data?.message || 'Error al renovar'
        this.$toast.error(message)
      }
    },

    formatDate(date) {
      return new Date(date).toLocaleString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      })
    },

    statusColor(status) {
      const colors = {
        pending: 'warning',
        active: 'success',
        completed: 'secondary',
        cancelled: 'danger',
      }
      return colors[status] || 'secondary'
    },
  },
})
</script>

<style scoped>
.mis-reservas {
  padding: 20px;
}

.btn {
  margin-right: 5px;
  margin-bottom: 5px;
}

.badge {
  padding: 5px 10px;
}

.text-warning {
  color: #ff9800;
  margin-top: 5px;
}
</style>
```

---

## 5. Modal para editar reserva

```vue
<template>
  <div class="modal" :class="{ 'is-active': true }">
    <div class="modal-background" @click="$emit('cerrar')"></div>
    <div class="modal-card">
      <header class="modal-card-head">
        <p class="modal-card-title">Modificar Reserva</p>
        <button class="delete" @click="$emit('cerrar')"></button>
      </header>

      <section class="modal-card-body">
        <form @submit.prevent="guardar">
          <!-- Vehículo (read-only) -->
          <div class="field">
            <label class="label">Vehículo</label>
            <div class="control">
              <input
                type="text"
                class="input"
                readonly
                :value="`${reserva.vehicle.brand} ${reserva.vehicle.model}`"
              />
            </div>
          </div>

          <!-- Fecha Inicio -->
          <div class="field">
            <label class="label">Fecha y Hora de Inicio</label>
            <div class="control">
              <input
                v-model="formData.start_date"
                type="datetime-local"
                class="input"
                required
              />
            </div>
          </div>

          <!-- Fecha Fin -->
          <div class="field">
            <label class="label">Fecha y Hora de Fin</label>
            <div class="control">
              <input
                v-model="formData.end_date"
                type="datetime-local"
                class="input"
                required
              />
            </div>
          </div>

          <!-- Punto de recogida -->
          <div class="field">
            <label class="label">Punto de Recogida</label>
            <div class="control">
              <select v-model="formData.pickup_location" class="input">
                <option v-for="ciudad in ciudades" :key="ciudad" :value="ciudad">
                  {{ ciudad }}
                </option>
              </select>
            </div>
          </div>

          <!-- Punto de devolución -->
          <div class="field">
            <label class="label">Punto de Devolución</label>
            <div class="control">
              <select v-model="formData.dropoff_location" class="input">
                <option v-for="ciudad in ciudades" :key="ciudad" :value="ciudad">
                  {{ ciudad }}
                </option>
              </select>
            </div>
          </div>

          <!-- Error si hay conflicto -->
          <div v-if="error" class="notification is-danger">
            {{ error }}
            <div v-if="disponibleEn" class="mt-2">
              <strong>Disponible desde:</strong> {{ disponibleEn }}
            </div>
          </div>
        </form>
      </section>

      <footer class="modal-card-foot">
        <button @click="$emit('cerrar')" class="button">Cancelar</button>
        <button @click="guardar" :disabled="cargando" class="button is-primary">
          {{ cargando ? 'Guardando...' : 'Guardar' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
  props: {
    reserva: {
      type: Object,
      required: true,
    },
    ciudades: {
      type: Array,
      required: true,
    },
  },
  data() {
    return {
      formData: {
        start_date: '',
        end_date: '',
        pickup_location: '',
        dropoff_location: '',
      },
      cargando: false,
      error: null,
      disponibleEn: null,
    }
  },
  mounted() {
    this.inicializarFormulario()
  },
  methods: {
    inicializarFormulario() {
      this.formData = {
        start_date: this.formatDatetimeLocal(this.reserva.start_date),
        end_date: this.formatDatetimeLocal(this.reserva.end_date),
        pickup_location: this.reserva.pickup_location,
        dropoff_location: this.reserva.dropoff_location,
      }
    },

    formatDatetimeLocal(dateString) {
      // Convierte ISO string a formato datetime-local
      const date = new Date(dateString)
      return date.toISOString().slice(0, 16)
    },

    async guardar() {
      try {
        this.error = null
        this.disponibleEn = null
        this.cargando = true
        this.$emit('guardar', this.formData)
      } catch (err) {
        if (err.response?.status === 409) {
          this.error = err.response.data.message
          this.disponibleEn = err.response.data.available_at
        } else {
          this.error = err.response?.data?.message || 'Error al guardar'
        }
      } finally {
        this.cargando = false
      }
    },
  },
})
</script>

<style scoped>
.modal.is-active {
  display: flex;
}

.mt-2 {
  margin-top: 10px;
}
</style>
```

---

## 6. Integración en rutas

Agregar a `router/index.js`:

```javascript
{
  path: '/mis-reservas',
  component: () => import('@/pages/MisReservas.vue'),
  meta: {
    requiresAuth: true,
  },
}
```

---

## 7. Enlace en navegación

Agregar en navbar/header:

```vue
<RouterLink to="/mis-reservas" class="nav-link">
  📅 Mis Reservas
</RouterLink>
```

---

## 8. Flujos de usuario

### Ver reservas
1. Usuario accede a `/mis-reservas`
2. Frontend llama `GET /api/v1/reservations`
3. Muestra tabla con todas sus reservas
4. Cada reserva muestra su estado (pending/active/completed/cancelled)

### Modificar reserva
1. Usuario hace click en "Modificar"
2. Se abre modal con formulario prellenado
3. Usuario cambia fechas/ubicaciones
4. Frontend valida fechas (end_date > start_date)
5. Al guardar, llama `PATCH /api/v1/reservations/{id}`
6. Si hay conflicto (409), muestra "Disponible desde..."
7. Si OK, refresca lista

### Cancelar reserva
1. Usuario hace click en "Cancelar"
2. Confirma con modal de confirmación
3. Frontend llama `DELETE /api/v1/reservations/{id}`
4. Si OK, refresca lista

### Renovar (ampliar tiempo)
1. Si reserva está activa y próxima a expirar, muestra botón "Ampliar"
2. Al hacer click, llama `POST /api/v1/reservations/{id}/renewal-intent`
3. Obtiene `payment_url` y redirige a pasarela de pago

---

## 9. Estados visuales

| Estado     | Color     | Acciones disponibles      | Notas                      |
|-----------|-----------|--------------------------|----------------------------|
| `pending` | ⚠️ Amber  | Modificar, Cancelar      | Reserva futura             |
| `active`  | ✅ Green  | Renovar, Cancelar       | Reserva en curso           |
| `completed` | ⚪ Gray | Ninguna                 | Reserva finalizada         |
| `cancelled` | ❌ Red   | Ninguna                 | Reserva cancelada          |

---

## 10. Manejo de errores

### 401 Unauthorized
- Token expirado/inválido
- Redirigir a login

### 403 Forbidden
- Intenta editar/borrar reserva de otro usuario
- Mostrar error: "No tienes permisos"

### 404 Not Found
- Reserva no existe o fue eliminada
- Refrescar lista

### 409 Conflict
- Al modificar: fechas solapan con otra reserva
- Mostrar: "No disponible. Disponible desde: X"

### 422 Unprocessable Entity
- Validación fallida (fechas inválidas, etc)
- Mostrar errores de validación
