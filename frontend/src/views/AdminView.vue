<script setup>
import { ref } from 'vue'
import AdminRooms from '@/components/AdminRooms.vue'
import AppHeader from '@/components/AppHeader.vue'
import BookingsPanel from '@/components/BookingsPanel.vue'

const tab = ref('rooms')
</script>

<template>
  <main class="page">
    <AppHeader />

    <section>
      <div class="list-head">
        <h2 class="list-title">Administración</h2>
      </div>

      <div class="tabs" role="tablist">
        <button
          type="button"
          role="tab"
          :class="{ active: tab === 'rooms' }"
          :aria-selected="tab === 'rooms'"
          @click="tab = 'rooms'"
        >
          Salas
        </button>
        <button
          type="button"
          role="tab"
          :class="{ active: tab === 'bookings' }"
          :aria-selected="tab === 'bookings'"
          @click="tab = 'bookings'"
        >
          Reservas
        </button>
      </div>

      <AdminRooms v-if="tab === 'rooms'" />
      <BookingsPanel v-else title="Reservas de todos los usuarios" all />
    </section>
  </main>
</template>
