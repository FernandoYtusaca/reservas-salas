<script setup>
import { onMounted, ref, watch } from 'vue'
import { api } from '@/services/api'
import BookingCard from '@/components/BookingCard.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const props = defineProps({
  title: { type: String, required: true },
  all: { type: Boolean, default: false },
})

const TABS = [
  { value: 'upcoming', label: 'Próximas', params: { scope: 'upcoming', status: 'confirmed' } },
  { value: 'past', label: 'Pasadas', params: { scope: 'past', status: 'confirmed' } },
  { value: 'cancelled', label: 'Canceladas', params: { status: 'cancelled' } },
]

const EMPTY = {
  upcoming: 'No hay reservas próximas.',
  past: 'Todavía no hay reservas pasadas.',
  cancelled: 'No hay reservas canceladas.',
}

const tab = ref('upcoming')
const page = ref(1)
const result = ref(null)
const loading = ref(false)
const error = ref('')
const notice = ref('')

const target = ref(null) // reserva que se está a punto de cancelar
const cancelling = ref(false)
const cancelError = ref('')

let lastRequest = 0

async function load() {
  const request = ++lastRequest

  loading.value = true
  error.value = ''

  try {
    const params = TABS.find((t) => t.value === tab.value).params
    const query = { ...params, page: page.value }

    if (props.all) query.all = 1

    const data = await api.get('/bookings', query)

    if (request === lastRequest) result.value = data
  } catch {
    if (request === lastRequest) error.value = 'No se pudieron cargar las reservas.'
  } finally {
    if (request === lastRequest) loading.value = false
  }
}

function dialogMessage() {
  const booking = target.value
  const who = props.all && booking.user ? `, reservada por ${booking.user.name}` : ''

  return `«${booking.title}» en ${booking.room?.name}${who}. Esta acción libera la franja para otras personas.`
}

function askCancel(booking) {
  cancelError.value = ''
  target.value = booking
}

function closeDialog() {
  if (!cancelling.value) target.value = null
}

async function confirmCancel() {
  cancelling.value = true
  cancelError.value = ''

  try {
    await api.post(`/bookings/${target.value.id}/cancel`)

    notice.value = `Reserva «${target.value.title}» cancelada.`
    target.value = null

    // Si era la única de la última página, se retrocede una página
    if (result.value.data.length === 1 && page.value > 1) page.value--
    else await load()
  } catch (e) {
    // Por ejemplo: ya estaba cancelada. Se muestra el motivo y se refresca la lista
    cancelError.value = e.message
    load()
  } finally {
    cancelling.value = false
  }
}

onMounted(load)

watch(tab, () => {
  notice.value = ''

  if (page.value !== 1) page.value = 1
  else load()
})

watch(page, load)
</script>

<template>
  <section>
    <div class="list-head">
      <h2 class="list-title">{{ title }}</h2>

      <div class="pills">
        <button
          v-for="t in TABS"
          :key="t.value"
          type="button"
          class="pill"
          :class="{ active: tab === t.value }"
          @click="tab = t.value"
        >
          {{ t.label }}
        </button>
      </div>
    </div>

    <p v-if="notice" class="success">{{ notice }}</p>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-else-if="loading && !result" class="muted">Cargando...</p>

    <div v-else-if="result && !result.data.length" class="panel empty-state">
      <p class="muted">{{ EMPTY[tab] }}</p>
      <RouterLink v-if="tab === 'upcoming' && !all" class="btn" :to="{ name: 'rooms' }">
        Reservar una sala
      </RouterLink>
    </div>

    <div v-else-if="result" class="booking-list" :class="{ dimmed: loading }">
      <BookingCard
        v-for="booking in result.data"
        :key="booking.id"
        :booking="booking"
        :show-user="all"
        :allow-started="all"
        @cancel="askCancel"
      />
    </div>

    <nav v-if="result && result.last_page > 1" class="pagination">
      <button class="secondary" :disabled="page <= 1" @click="page--">Anterior</button>
      <span>Página {{ result.current_page }} de {{ result.last_page }}</span>
      <button class="secondary" :disabled="page >= result.last_page" @click="page++">
        Siguiente
      </button>
    </nav>

    <ConfirmDialog
      v-if="target"
      title="¿Cancelar esta reserva?"
      :message="dialogMessage()"
      confirm-label="Sí, cancelar reserva"
      cancel-label="Volver"
      danger
      :loading="cancelling"
      :error="cancelError"
      @confirm="confirmCancel"
      @cancel="closeDialog"
    />
  </section>
</template>
