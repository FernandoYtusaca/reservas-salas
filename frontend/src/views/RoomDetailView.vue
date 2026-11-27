<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/services/api'
import AppHeader from '@/components/AppHeader.vue'
import AppIcon from '@/components/AppIcon.vue'
import AvailabilityTimeline from '@/components/AvailabilityTimeline.vue'
import { addDays, formatDuration, formatLongDate, formatTime, toLocalDate } from '@/utils/format'

const STEP = 15 * 60 * 1000
const HOUR = 60 * 60 * 1000

const route = useRoute()
const id = route.params.id
const today = toLocalDate()
const browserZone = Intl.DateTimeFormat().resolvedOptions().timeZone

const room = ref(null)
const roomError = ref('')

const date = ref(today)
const availability = ref(null)
const availabilityError = ref('')
const loading = ref(false)

// Inicio y fin se guardan en milisegundos: comparar números es más fiable que comparar textos
const form = reactive({ title: '', start: null, end: null })
const errors = ref({})
const message = ref('')
const saving = ref(false)
const success = ref(false)

const zoneMismatch = computed(
  () => availability.value && availability.value.timezone !== browserZone,
)

const ranges = computed(() =>
  (availability.value?.free ?? []).map((range) => ({
    start: new Date(range.starts_at).getTime(),
    end: new Date(range.ends_at).getTime(),
  })),
)

// Cada múltiplo de 15 minutos de cada tramo libre es un inicio posible
const startOptions = computed(() => {
  const options = []

  for (const range of ranges.value) {
    for (let t = range.start; t + STEP <= range.end; t += STEP) {
      options.push({ value: t, label: formatTime(t) })
    }
  }

  return options
})

const currentRange = computed(() =>
  ranges.value.find((range) => form.start >= range.start && form.start < range.end),
)

// El fin llega hasta donde acaba el tramo o hasta la duración máxima, lo que ocurra antes
const endOptions = computed(() => {
  if (!currentRange.value || form.start === null) return []

  const limit = Math.min(
    currentRange.value.end,
    form.start + (room.value?.max_booking_minutes ?? 0) * 60 * 1000,
  )
  const options = []

  for (let t = form.start + STEP; t <= limit; t += STEP) {
    const minutes = (t - form.start) / 60000
    options.push({ value: t, label: `${formatTime(t)} · ${formatDuration(minutes)}` })
  }

  return options
})

const summary = computed(() =>
  form.start && form.end
    ? `${formatLongDate(date.value)}, de ${formatTime(form.start)} a ${formatTime(form.end)} (${formatDuration((form.end - form.start) / 60000)})`
    : '',
)

async function loadRoom() {
  try {
    room.value = await api.get(`/rooms/${id}`)
  } catch (e) {
    roomError.value = e.status === 404 ? 'No se encontró la sala.' : 'No se pudo cargar la sala.'
  }
}

// Si el usuario cambia de día rápido, solo se hace caso a la última respuesta
let lastRequest = 0

async function loadAvailability() {
  const request = ++lastRequest

  loading.value = true
  availabilityError.value = ''

  try {
    const data = await api.get(`/rooms/${id}/availability`, { date: date.value })

    if (request === lastRequest) availability.value = data
  } catch {
    if (request === lastRequest) {
      availabilityError.value = 'No se pudo cargar la disponibilidad.'
    }
  } finally {
    if (request === lastRequest) loading.value = false
  }
}

function shift(days) {
  date.value = addDays(date.value, days)
}

function onPick(ms) {
  form.start = ms
}

async function submit() {
  errors.value = {}
  message.value = ''
  success.value = false
  saving.value = true

  try {
    await api.post('/bookings', {
      room_id: room.value.id,
      title: form.title,
      starts_at: new Date(form.start).toISOString(),
      ends_at: new Date(form.end).toISOString(),
    })

    success.value = true
    form.title = ''
    await loadAvailability()
  } catch (e) {
    if (e.status === 422) {
      errors.value = e.errors
    } else if (e.status === 409) {
      // Otra persona reservó esa franja mientras tanto: se refresca lo que se ve
      message.value = e.message
      await loadAvailability()
    } else {
      message.value = 'No se pudo crear la reserva.'
    }
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadRoom()
  if (room.value) await loadAvailability()
})

watch(date, (value) => {
  if (!value) return

  if (value < today) {
    date.value = today
    return
  }

  success.value = false
  loadAvailability()
})

// Si el inicio elegido deja de existir (al cambiar de día), se elige el primero
watch(startOptions, (options) => {
  if (!options.some((option) => option.value === form.start)) {
    form.start = options[0]?.value ?? null
  }
})

// Fin por defecto: una hora después del inicio, o lo máximo posible
watch(endOptions, (options) => {
  if (options.some((option) => option.value === form.end)) return

  const oneHour = options.find((option) => option.value === form.start + HOUR)

  form.end = (oneHour ?? options.at(-1))?.value ?? null
})
</script>

<template>
  <main class="page">
    <AppHeader />

    <RouterLink :to="{ name: 'rooms' }" class="back">← Volver a las salas</RouterLink>

    <p v-if="roomError" class="error">{{ roomError }}</p>
    <p v-else-if="!room" class="muted">Cargando...</p>

    <template v-else>
      <section class="panel room-hero">
        <div>
          <h2>{{ room.name }}</h2>
          <p class="muted">{{ room.description || 'Sin descripción.' }}</p>
        </div>

        <ul class="room-meta">
          <li>
            <AppIcon name="users" :size="16" />
            {{ room.capacity }} {{ room.capacity === 1 ? 'persona' : 'personas' }}
          </li>
          <li>
            <AppIcon name="clock" :size="16" />
            {{ room.opens_at }} – {{ room.closes_at }}
          </li>
          <li>Máx. {{ formatDuration(room.max_booking_minutes) }} por reserva</li>
        </ul>
      </section>

      <div class="booking-layout">
        <section class="panel">
          <div class="day-nav">
            <button
              type="button"
              class="secondary icon-btn"
              :disabled="date <= today"
              aria-label="Día anterior"
              @click="shift(-1)"
            >
              <AppIcon name="chevron-left" />
            </button>

            <div class="day-title">
              <strong>{{ formatLongDate(date) }}</strong>
              <input v-model="date" type="date" :min="today" />
            </div>

            <button
              type="button"
              class="secondary icon-btn"
              aria-label="Día siguiente"
              @click="shift(1)"
            >
              <AppIcon name="chevron-right" />
            </button>

            <button
              type="button"
              class="secondary"
              :disabled="date === today"
              @click="date = today"
            >
              Hoy
            </button>
          </div>

          <p v-if="zoneMismatch" class="notice">
            Los horarios se muestran en tu zona horaria ({{ browserZone }}); la sala opera en
            {{ availability.timezone }}.
          </p>

          <p v-if="availabilityError" class="error">{{ availabilityError }}</p>
          <p v-else-if="!availability" class="muted">Cargando disponibilidad...</p>

          <div v-else :class="{ dimmed: loading }">
            <AvailabilityTimeline
              :availability="availability"
              :start="form.start"
              :end="form.end"
              @pick="onPick"
            />
          </div>
        </section>

        <form class="panel booking-form" @submit.prevent="submit">
          <h2>Reservar</h2>

          <p v-if="!room.is_active" class="notice">Esta sala está inactiva y no admite reservas.</p>

          <label>
            Título
            <input
              v-model="form.title"
              type="text"
              maxlength="120"
              required
              placeholder="Ej. Reunión de planificación"
              :disabled="!room.is_active"
            />
            <small v-if="errors.title" class="error">{{ errors.title[0] }}</small>
          </label>

          <div class="booking-times">
            <label>
              Inicio
              <select v-model="form.start" :disabled="!startOptions.length || !room.is_active">
                <option v-for="o in startOptions" :key="o.value" :value="o.value">
                  {{ o.label }}
                </option>
              </select>
              <small v-if="errors.starts_at" class="error">{{ errors.starts_at[0] }}</small>
            </label>

            <label>
              Fin
              <select v-model="form.end" :disabled="!endOptions.length || !room.is_active">
                <option v-for="o in endOptions" :key="o.value" :value="o.value">
                  {{ o.label }}
                </option>
              </select>
              <small v-if="errors.ends_at" class="error">{{ errors.ends_at[0] }}</small>
            </label>
          </div>

          <p v-if="summary" class="booking-summary">{{ summary }}</p>

          <p v-if="errors.room_id" class="error">{{ errors.room_id[0] }}</p>
          <p v-if="message" class="error">{{ message }}</p>

          <p v-if="success" class="success">
            ¡Reserva confirmada!
            <RouterLink :to="{ name: 'my-bookings' }">Ver mis reservas</RouterLink>
          </p>

          <button :disabled="saving || !form.start || !form.end || !room.is_active">
            {{ saving ? 'Reservando...' : 'Confirmar reserva' }}
          </button>
        </form>
      </div>
    </template>
  </main>
</template>
