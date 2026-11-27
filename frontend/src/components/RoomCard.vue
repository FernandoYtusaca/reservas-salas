<script setup>
import AppIcon from '@/components/AppIcon.vue'
import { formatDuration } from '@/utils/format'

defineProps({
  room: { type: Object, required: true },
})
</script>

<template>
  <article class="room-card" :class="{ inactive: !room.is_active }">
    <header class="room-head">
      <span class="room-icon"><AppIcon name="calendar" :size="22" /></span>
      <div>
        <h3 class="room-name">{{ room.name }}</h3>
        <span v-if="!room.is_active" class="badge badge-danger">Inactiva</span>
      </div>
    </header>

    <p class="room-description">{{ room.description || 'Sin descripción.' }}</p>

    <ul class="room-meta">
      <li>
        <AppIcon name="users" :size="16" />
        {{ room.capacity }} {{ room.capacity === 1 ? 'persona' : 'personas' }}
      </li>
      <li>
        <AppIcon name="clock" :size="16" />
        {{ room.opens_at }} – {{ room.closes_at }}
      </li>
    </ul>

    <footer class="room-foot">
      <span class="muted">Máx. {{ formatDuration(room.max_booking_minutes) }} por reserva</span>
      <RouterLink class="btn" :to="{ name: 'room-detail', params: { id: room.id } }">
        {{ room.is_active ? 'Reservar' : 'Ver' }}
      </RouterLink>
    </footer>
  </article>
</template>
