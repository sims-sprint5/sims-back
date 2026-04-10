# Frontend - Dos Vistas: Mapa vs Reservas

Especificación de cómo mostrar los vehículos en dos contextos diferentes.

---

## 1. Vista MAPA (solo disponibles)

**Endpoint:** `GET /api/v1/vehicles`

**Comportamiento:**
- Usuario normal: VE **SOLO** vehículos con `status = "available"`
- Admin: VE todos los vehículos

**Respuesta:**
```json
{
  "data": [
    {
      "vehicle_id": 1,
      "license_plate": "AB-1234",
      "brand": "Toyota",
      "model": "Corolla",
      "status": "available",
      "current_latitude": 41.38,
      "current_longitude": 2.15,
      "next_reservation": {
        "start_date": "2026-04-30T10:00:00Z",
        "end_date": "2026-04-30T18:00:00Z",
        "user_name": "Juan García"
      }
    }
  ]
}
```

**Interfaz del mapa:**
- Mostrar icono de vehículo
- Opcional: mostrar popup con "Próxima reserva: 30/04 10:00 - 18:00 (Juan García)"
- Botón: "Reservar"

---

## 2. Vista RESERVAS (disponibles + reservados + calendario)

**Endpoint:** `GET /api/v1/vehicles-calendar`

**Comportamiento:**
- Usuario normal: VE **TODOS** los vehículos (available + reserved)
- Incluye calendario de reservas

**Respuesta:**
```json
{
  "data": [
    {
      "vehicle_id": 1,
      "license_plate": "AB-1234",
      "brand": "Toyota",
      "model": "Corolla",
      "color": "Blue",
      "year": 2023,
      "status": "available",
      "current_latitude": 41.38,
      "current_longitude": 2.15,
      "next_available_at": "2026-04-08T18:00:00Z",
      "calendar_reservations": [
        {
          "start_date": "2026-04-30T10:00:00Z",
          "end_date": "2026-04-30T18:00:00Z",
          "user_name": "Juan García",
          "status": "pending"
        },
        {
          "start_date": "2026-05-15T08:00:00Z",
          "end_date": "2026-05-15T20:00:00Z",
          "user_name": "María López",
          "status": "pending"
        }
      ]
    }
  ]
}
```

---

## 3. Flujo de "Prereserva"

Una **prereserva** es simplemente una reserva normal creada para una fecha futura.

### Caso 1: Vehículo está DISPONIBLE
```
Usuario hace click en "Reservar"
  ↓
Se abre modal de reserva
  ↓
Usuario selecciona fechas (start_date, end_date)
  ↓
Frontend llama: POST /api/v1/reservations
  ↓
Se crea la reserva con status = "pending" (porque start_date > now)
  ↓
Vehículo sigue mostrándose como "available"
```

### Caso 2: Vehículo está RESERVADO por otro usuario (prereserva)
```
Usuario ve el vehículo con calendario mostrando:
  [Gris] 30/04 10:00 - 30/04 18:00 (Juan García)
  [Negro] (resto de días disponibles)
  ↓
Usuario hace click en "Dar de alta para después"
  ↓
Se abre modal de reserva
  ↓
Frontend muestra:
  - Fechas sugeridas: start_date = fin de la última reserva
  - Usuario puede seleccionar otras fechas que no conflicten
  ↓
Frontend llama: GET /api/v1/reservations/check-availability
  ↓
Si disponible, usuario hace POST /api/v1/reservations
  ↓
Se crea la reserva con status = "pending"
```

---

## 4. Componente Vue - Vista de Reservas

```vue
<template>
  <div class="reservas-section">
    <h2>Buscar y Reservar Vehículos</h2>

    <!-- Tabla de vehículos -->
    <div class="vehicles-table">
      <table class="table">
        <thead>
          <tr>
            <th>Vehículo</th>
            <th>Matrícula</th>
            <th>Estado</th>
            <th>Disponibilidad</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="vehicle in vehicles" :key="vehicle.vehicle_id">
            <td>
              {{ vehicle.brand }} {{ vehicle.model }}
              <br>
              <small class="text-muted">{{ vehicle.color }} ({{ vehicle.year }})</small>
            </td>
            <td>{{ vehicle.license_plate }}</td>
            <td>
              <span :class="`badge badge-${vehicle.status === 'available' ? 'success' : 'danger'}`">
                {{ vehicle.status }}
              </span>
            </td>
            <td>
              <!-- Botón para ver/expandir calendario -->
              <button
                @click="toggleCalendar(vehicle.vehicle_id)"
                class="btn btn-sm btn-info"
              >
                📅 Ver disponibilidad
              </button>
            </td>
            <td>
              <button
                @click="abrirReserva(vehicle)"
                class="btn btn-sm btn-primary"
              >
                ✓ Reservar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Calendario desplegable por vehículo -->
    <div v-if="calendarioAbierto" class="calendar-section">
      <VehicleCalendar
        :vehicle="vehiculoSeleccionado"
        @cerrar="calendarioAbierto = false"
        @prereservar="abrirReserva"
      />
    </div>

    <!-- Modal de reserva/prereserva -->
    <ReservaModal
      v-if="mostrarReserva"
      :vehicle="vehiculoSeleccionado"
      :suggested-start-date="fechaSugerida"
      @guardar="crearReserva"
      @cerrar="mostrarReserva = false"
    />
  </div>
</template>

<script>
import { defineComponent } from 'vue'
import api from '@/services/api.service'
import VehicleCalendar from '@/components/VehicleCalendar.vue'
import ReservaModal from '@/components/ReservaModal.vue'

export default defineComponent({
  components: {
    VehicleCalendar,
    ReservaModal,
  },
  data() {
    return {
      vehicles: [],
      loading: true,
      error: null,
      calendarioAbierto: false,
      vehiculoSeleccionado: null,
      mostrarReserva: false,
      fechaSugerida: null,
    }
  },
  created() {
    this.cargarVehiculos()
  },
  methods: {
    async cargarVehiculos() {
      try {
        this.loading = true
        const response = await api.get('/vehicles-calendar')
        this.vehicles = response.data.data || response.data
        this.error = null
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar vehículos'
      } finally {
        this.loading = false
      }
    },

    toggleCalendar(vehicleId) {
      const vehicle = this.vehicles.find(v => v.vehicle_id === vehicleId)
      if (this.calendarioAbierto && this.vehiculoSeleccionado?.vehicle_id === vehicleId) {
        this.calendarioAbierto = false
      } else {
        this.vehiculoSeleccionado = vehicle
        this.calendarioAbierto = true
      }
    },

    abrirReserva(vehicle, sugeridoStart = null) {
      this.vehiculoSeleccionado = vehicle
      // Si es prereserva, sugerir la fecha siguiente a la última reserva
      if (sugeridoStart) {
        this.fechaSugerida = sugeridoStart
      } else if (vehicle.next_available_at) {
        this.fechaSugerida = vehicle.next_available_at
      }
      this.mostrarReserva = true
      this.calendarioAbierto = false
    },

    async crearReserva(datosReserva) {
      try {
        const response = await api.post('/reservations', {
          vehicle_id: this.vehiculoSeleccionado.vehicle_id,
          start_date: datosReserva.start_date,
          end_date: datosReserva.end_date,
          pickup_location: datosReserva.pickup_location,
          dropoff_location: datosReserva.dropoff_location,
        })
        this.$toast.success('Reserva creada exitosamente')
        this.mostrarReserva = false
        this.cargarVehiculos()
      } catch (err) {
        const message = err.response?.data?.message || 'Error al crear reserva'
        this.$toast.error(message)
      }
    },
  },
})
</script>
```

---

## 5. Componente Calendario

```vue
<template>
  <div class="calendar-container">
    <div class="calendar-header">
      <h3>{{ vehicle.brand }} {{ vehicle.model }} - {{ vehicle.license_plate }}</h3>
      <button @click="$emit('cerrar')" class="btn-close">✕</button>
    </div>

    <div class="calendar-grid">
      <!-- Mostrar 3 meses siguiente -->
      <div v-for="month in monthsToShow" :key="month" class="month-calendar">
        <h4>{{ formatMonth(month) }}</h4>
        <div class="days-grid">
          <div
            v-for="day in getDaysInMonth(month)"
            :key="`${month}-${day}`"
            :class="getDayClass(month, day)"
            @click="handleDayClick(month, day)"
          >
            {{ day }}
          </div>
        </div>
      </div>
    </div>

    <div class="calendar-legend">
      <div class="legend-item">
        <span class="color available"></span> Disponible
      </div>
      <div class="legend-item">
        <span class="color reserved"></span> Reservado
      </div>
    </div>
  </div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
  props: {
    vehicle: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      monthsToShow: [
        this.getNextMonth(0),
        this.getNextMonth(1),
        this.getNextMonth(2),
      ],
    }
  },
  methods: {
    getNextMonth(offset) {
      const date = new Date()
      date.setMonth(date.getMonth() + offset)
      return new Date(date.getFullYear(), date.getMonth(), 1)
    },

    getDaysInMonth(dateObj) {
      const year = dateObj.getFullYear()
      const month = dateObj.getMonth()
      const lastDay = new Date(year, month + 1, 0).getDate()
      return Array.from({ length: lastDay }, (_, i) => i + 1)
    },

    formatMonth(dateObj) {
      return dateObj.toLocaleString('es-ES', { month: 'long', year: 'numeric' })
    },

    isDayReserved(year, month, day) {
      const checkDate = new Date(year, month, day)
      return this.vehicle.calendar_reservations.some(res => {
        const startDate = new Date(res.start_date)
        const endDate = new Date(res.end_date)
        return checkDate >= startDate && checkDate < endDate
      })
    },

    getDayClass(dateObj, day) {
      const year = dateObj.getFullYear()
      const month = dateObj.getMonth()
      const isReserved = this.isDayReserved(year, month, day)
      const isToday = this.isToday(new Date(year, month, day))
      
      return {
        'day': true,
        'reserved': isReserved,
        'available': !isReserved,
        'today': isToday,
      }
    },

    isToday(date) {
      const today = new Date()
      return date.getDate() === today.getDate() &&
             date.getMonth() === today.getMonth() &&
             date.getFullYear() === today.getFullYear()
    },

    handleDayClick(dateObj, day) {
      const year = dateObj.getFullYear()
      const month = dateObj.getMonth()
      const clickedDate = new Date(year, month, day)

      // Si está reservado, sugerir comenzar después de esa reserva
      if (this.isDayReserved(year, month, day)) {
        const reservation = this.vehicle.calendar_reservations.find(res => {
          const startDate = new Date(res.start_date)
          const endDate = new Date(res.end_date)
          return clickedDate >= startDate && clickedDate < endDate
        })
        if (reservation) {
          this.$emit('prereservar', this.vehicle, reservation.end_date)
        }
      } else {
        // Si está disponible, permitir preresrva desde ese día
        this.$emit('prereservar', this.vehicle, clickedDate.toISOString())
      }
    },
  },
})
</script>

<style scoped>
.calendar-container {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  margin: 20px 0;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.month-calendar h4 {
  text-align: center;
  margin-bottom: 10px;
  font-weight: bold;
}

.days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 5px;
}

.day {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #ccc;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 500;
}

.day.available {
  background-color: #e8f5e9; /* Verde claro */
  color: #000;
}

.day.reserved {
  background-color: #f3f3f3; /* Gris */
  color: #666;
  cursor: not-allowed;
}

.day.available:hover {
  background-color: #c8e6c9; /* Verde más oscuro */
}

.day.today {
  border: 2px solid #ff9800;
  font-weight: bold;
}

.calendar-legend {
  display: flex;
  gap: 30px;
  justify-content: center;
  padding: 10px;
  background-color: #f5f5f5;
  border-radius: 4px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.legend-item .color {
  width: 20px;
  height: 20px;
  border-radius: 4px;
  border: 1px solid #999;
}

.legend-item .color.available {
  background-color: #e8f5e9;
}

.legend-item .color.reserved {
  background-color: #f3f3f3;
}
</style>
```

---

## 6. Resumen de flujos

### Mapa (GET /api/v1/vehicles)
```
Usuario abre aplicación
  ↓
Ve mapa con vehículos DISPONIBLES
  ↓
Hace click en vehículo
  ↓
Abre modal de reserva para ESTE mes/semana
```

### Sección Reservas (GET /api/v1/vehicles-calendar)
```
Usuario va a "Reservar" o "Buscar vehículos"
  ↓
Ve tabla con TODOS los vehículos (available + reserved)
  ↓
Ve para cada vehículo:
  - Estado (available/reserved)
  - Botón "Ver disponibilidad"
  ↓
Hace click en "Ver disponibilidad"
  ↓
Se abre calendario con 3 meses
  - Días DISPONIBLES: verdes, clickeables
  - Días RESERVADOS: grises, no clickeables
  ↓
Hace click en día disponible o en el último día de una reserva
  ↓
Se abre modal de reserva
  ↓
Por defecto, start_date = fecha sugerida (hoy o fin de reserva anterior)
  ↓
Usuario selecciona end_date
  ↓
Frontend llama GET /api/v1/reservations/check-availability
  ↓
Si OK, usuario confirma
  ↓
Backend crea reserva con status = "pending"
```

---

## 7. Rutas necesarias - RESUMEN

| Ruta | Método | Descripción |
|------|--------|-------------|
| `/api/v1/vehicles` | GET | Mapa: solo vehículos disponibles |
| `/api/v1/vehicles-calendar` | GET | Reservas: todos vehículos + calendarios |
| `/api/v1/vehicles/{id}` | GET | Detalle de vehículo |
| `/api/v1/reservations` | POST | Crear reserva (incluye prereservas) |
| `/api/v1/reservations/check-availability` | GET | Verificar disponibilidad antes de reservar |
| `/api/v1/reservations` | GET | Mis reservas |
| `/api/v1/reservations/{id}` | PATCH | Editar reserva |
| `/api/v1/reservations/{id}` | DELETE | Cancelar reserva |

