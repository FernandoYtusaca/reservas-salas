<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '@/services/api'
import AppHeader from '@/components/AppHeader.vue'
import AppIcon from '@/components/AppIcon.vue'
import RoomCard from '@/components/RoomCard.vue'

const CAPACITY_FILTERS = [
  { value: 0, label: 'Todas' },
  { value: 4, label: '4+ personas' },
  { value: 8, label: '8+ personas' },
  { value: 12, label: '12+ personas' },
]

const rooms = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const minCapacity = ref(0)

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()

  return rooms.value.filter(
    (room) =>
      room.capacity >= minCapacity.value &&
      (!term || `${room.name} ${room.description ?? ''}`.toLowerCase().includes(term)),
  )
})

async function load() {
  try {
    rooms.value = await api.get('/rooms')
  } catch {
    error.value = 'No se pudieron cargar las salas.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="page">
    <AppHeader />

    <section>
      <div class="list-head">
        <h2 class="list-title">Salas</h2>

        <div class="pills">
          <button
            v-for="f in CAPACITY_FILTERS"
            :key="f.value"
            type="button"
            class="pill"
            :class="{ active: minCapacity === f.value }"
            @click="minCapacity = f.value"
          >
            {{ f.label }}
          </button>
        </div>
      </div>

      <label class="search">
        <AppIcon name="search" :size="18" />
        <input v-model="search" type="search" placeholder="Buscar por nombre o descripción" />
      </label>

      <p v-if="error" class="error">{{ error }}</p>

      <div v-else-if="loading" class="rooms-grid">
        <div v-for="n in 3" :key="n" class="skeleton" />
      </div>

      <p v-else-if="!rooms.length" class="muted">Todavía no hay salas disponibles.</p>
      <p v-else-if="!filtered.length" class="muted">Ninguna sala coincide con tu búsqueda.</p>

      <div v-else class="rooms-grid">
        <RoomCard v-for="room in filtered" :key="room.id" :room="room" />
      </div>
    </section>
  </main>
</template>
