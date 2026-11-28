<script setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue'
import { api } from '@/services/api'

const props = defineProps({
  room: { type: Object, default: null },
})
const emit = defineEmits(['saved', 'close'])

const form = reactive({
  name: props.room?.name ?? '',
  description: props.room?.description ?? '',
  capacity: props.room?.capacity ?? 6,
  opens_at: props.room?.opens_at ?? '08:00',
  closes_at: props.room?.closes_at ?? '20:00',
  max_booking_minutes: props.room?.max_booking_minutes ?? 120,
  is_active: props.room?.is_active ?? true,
})

const errors = ref({})
const message = ref('')
const saving = ref(false)

async function submit() {
  errors.value = {}
  message.value = ''
  saving.value = true

  try {
    const saved = props.room
      ? await api.put(`/rooms/${props.room.id}`, form)
      : await api.post('/rooms', form)

    emit('saved', saved, !props.room)
  } catch (e) {
    if (e.status === 422) errors.value = e.errors
    else message.value = 'No se pudo guardar la sala.'
  } finally {
    saving.value = false
  }
}

function onKey(event) {
  if (event.key === 'Escape' && !saving.value) emit('close')
}

onMounted(() => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div class="modal-backdrop" @click.self="!saving && emit('close')">
      <form class="dialog dialog-wide" @submit.prevent="submit">
        <h2>{{ room ? 'Editar sala' : 'Nueva sala' }}</h2>

        <label>
          Nombre
          <input v-model="form.name" type="text" maxlength="100" required />
          <small v-if="errors.name" class="error">{{ errors.name[0] }}</small>
        </label>

        <label>
          Descripción (opcional)
          <textarea v-model="form.description" rows="3" maxlength="1000" />
          <small v-if="errors.description" class="error">{{ errors.description[0] }}</small>
        </label>

        <div class="form-grid-2">
          <label>
            Capacidad
            <input v-model.number="form.capacity" type="number" min="1" max="500" required />
            <small v-if="errors.capacity" class="error">{{ errors.capacity[0] }}</small>
          </label>

          <label>
            Duración máxima (minutos)
            <input
              v-model.number="form.max_booking_minutes"
              type="number"
              min="15"
              max="480"
              step="15"
              required
            />
            <small v-if="errors.max_booking_minutes" class="error">
              {{ errors.max_booking_minutes[0] }}
            </small>
          </label>

          <label>
            Apertura
            <input v-model="form.opens_at" type="time" required />
            <small v-if="errors.opens_at" class="error">{{ errors.opens_at[0] }}</small>
          </label>

          <label>
            Cierre
            <input v-model="form.closes_at" type="time" required />
            <small v-if="errors.closes_at" class="error">{{ errors.closes_at[0] }}</small>
          </label>
        </div>

        <label class="check">
          <input v-model="form.is_active" type="checkbox" />
          Sala activa (admite reservas)
        </label>

        <p v-if="message" class="error">{{ message }}</p>

        <div class="form-actions">
          <button :disabled="saving">{{ saving ? 'Guardando...' : 'Guardar' }}</button>
          <button type="button" class="secondary" :disabled="saving" @click="emit('close')">
            Cancelar
          </button>
        </div>
      </form>
    </div>
  </Teleport>
</template>
