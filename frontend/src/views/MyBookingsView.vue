<script setup>
import { onMounted, ref, watch } from 'vue'
import { api } from '@/services/api'
import AppHeader from '@/components/AppHeader.vue'
import BookingCard from '@/components/BookingCard.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const TABS = [
  { value: 'upcoming', label: 'Próximas', params: { scope: 'upcoming', status: 'confirmed' } },
  { value: 'past', label: 'Pasadas', params: { scope: 'past', status: 'confirmed' } },
  { value: 'cancelled', label: 'Canceladas', params: { status: 'cancelled' } },
]

const EMPTY = {
  upcoming: 'No tienes reservas próximas.',
  past: 'Todavía no tienes reservas pasadas.',
  cancelled: 'No has cancelado ninguna reserva.',
}

const tab = ref('upcoming')
const page = ref(1)
const result = ref(null)
const loading = ref(false)
const error = ref('')
const notice = ref('')

const target = ref(null)
const cancelling = ref(false)
const cancelError = ref('')

let lastRequest = 0

async function load() {
  const request = ++lastRequest

  loading.value = true
  error.value = ''

  try {
    const params = TABS.find((t) => t.value === tab.value).params
    const data = await api.get('/bookings', { ...params, page: page.value })

    if (request === lastRequest) result.value = data
  } catch {
    if (request === lastRequest) error.value = 'No se pudieron cargar tus reservas.'
  } finally {
    if (request === lastRequest) loading.value = false
  }
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
    // Si estaba cancelada o ya empezó, se muestra el motivo y se refresca la lista
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
  <main class="page">
    <AppHeader />

    <section>
      <div class="list-head">
        <h2 class="list-title">Mis reservas</h2>

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
        <RouterLink v-if="tab === 'upcoming'" class="btn" :to="{ name: 'rooms' }">
          Reservar una sala
        </RouterLink>
      </div>

      <div v-else-if="result" class="booking-list" :class="{ dimmed: loading }">
        <BookingCard
          v-for="booking in result.data"
          :key="booking.id"
          :booking="booking"
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
    </section>

    <ConfirmDialog
      v-if="target"
      title="¿Cancelar esta reserva?"
      :message="`«${target.title}» en ${target.room?.name}. Esta acción libera la franja para otras personas.`"
      confirm-label="Sí, cancelar reserva"
      cancel-label="Volver"
      danger
      :loading="cancelling"
      :error="cancelError"
      @confirm="confirmCancel"
      @cancel="closeDialog"
    />
  </main>
</template>
