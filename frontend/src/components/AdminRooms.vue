<script setup>
import { onMounted, ref } from 'vue'
import { api } from '@/services/api'
import AppIcon from '@/components/AppIcon.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import RoomFormDialog from '@/components/RoomFormDialog.vue'
import { formatDuration } from '@/utils/format'

const rooms = ref([])
const loading = ref(true)
const error = ref('')
const notice = ref('')

const formOpen = ref(false)
const current = ref(null) // sala que se edita; null = sala nueva

const deleting = ref(null)
const deleteError = ref('')
const deleteBusy = ref(false)
const togglingId = ref(null)

async function load() {
  try {
    rooms.value = await api.get('/rooms')
  } catch {
    error.value = 'No se pudieron cargar las salas.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  current.value = null
  formOpen.value = true
}

function openEdit(room) {
  current.value = room
  formOpen.value = true
}

async function onSaved(room, created) {
  formOpen.value = false
  notice.value = created ? `Sala «${room.name}» creada.` : `Sala «${room.name}» actualizada.`

  await load()
}

// El servidor pide la sala completa al actualizar, se reenvían todos sus datos
async function toggleActive(room) {
  togglingId.value = room.id
  notice.value = ''
  error.value = ''

  try {
    await api.put(`/rooms/${room.id}`, {
      name: room.name,
      description: room.description,
      capacity: room.capacity,
      opens_at: room.opens_at,
      closes_at: room.closes_at,
      max_booking_minutes: room.max_booking_minutes,
      is_active: !room.is_active,
    })

    notice.value = room.is_active
      ? `Sala «${room.name}» desactivada.`
      : `Sala «${room.name}» activada.`

    await load()
  } catch {
    error.value = 'No se pudo cambiar el estado de la sala.'
  } finally {
    togglingId.value = null
  }
}

function askDelete(room) {
  deleteError.value = ''
  deleting.value = room
}

function closeDelete() {
  if (!deleteBusy.value) deleting.value = null
}

async function confirmDelete() {
  deleteBusy.value = true
  deleteError.value = ''

  try {
    await api.delete(`/rooms/${deleting.value.id}`)

    notice.value = `Sala «${deleting.value.name}» eliminada.`
    deleting.value = null

    await load()
  } catch (e) {
    // El servidor rechaza borrar una sala con reservas y sugiere desactivarla
    deleteError.value = e.status === 409 ? e.message : 'No se pudo eliminar la sala.'
  } finally {
    deleteBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="panel">
    <div class="panel-head">
      <h2>Salas</h2>
      <button type="button" class="with-icon" @click="openCreate">
        <AppIcon name="plus" :size="16" />
        Nueva sala
      </button>
    </div>

    <p v-if="notice" class="success">{{ notice }}</p>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-else-if="loading" class="muted">Cargando...</p>
    <p v-else-if="!rooms.length" class="muted">Todavía no hay salas. Crea la primera.</p>

    <div v-else class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Sala</th>
            <th>Capacidad</th>
            <th>Horario</th>
            <th>Máx. reserva</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="room in rooms" :key="room.id">
            <td>
              <div class="room-cell">
                <strong>{{ room.name }}</strong>
                <span class="muted">{{ room.description || 'Sin descripción' }}</span>
              </div>
            </td>
            <td>{{ room.capacity }}</td>
            <td>{{ room.opens_at }} – {{ room.closes_at }}</td>
            <td>{{ formatDuration(room.max_booking_minutes) }}</td>
            <td>
              <span class="badge" :class="room.is_active ? 'badge-ok' : 'badge-danger'">
                {{ room.is_active ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="row-actions">
              <button type="button" class="link" @click="openEdit(room)">Editar</button>
              <button
                type="button"
                class="link"
                :disabled="togglingId === room.id"
                @click="toggleActive(room)"
              >
                {{ room.is_active ? 'Desactivar' : 'Activar' }}
              </button>
              <button type="button" class="link danger" @click="askDelete(room)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <RoomFormDialog v-if="formOpen" :room="current" @saved="onSaved" @close="formOpen = false" />

    <ConfirmDialog
      v-if="deleting"
      title="¿Eliminar esta sala?"
      :message="`«${deleting.name}» se eliminará de forma permanente. Si solo quieres que deje de aceptar reservas, mejor desactívala.`"
      confirm-label="Sí, eliminar sala"
      danger
      :loading="deleteBusy"
      :error="deleteError"
      @confirm="confirmDelete"
      @cancel="closeDelete"
    />
  </section>
</template>
