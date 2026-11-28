<script setup>
import { computed } from 'vue'
import AppIcon from '@/components/AppIcon.vue'
import { formatDuration, formatShortDate, formatTime, toDate } from '@/utils/format'

const props = defineProps({
  booking: { type: Object, required: true },
  showUser: { type: Boolean, default: false },
  allowStarted: { type: Boolean, default: false },
})
const emit = defineEmits(['cancel'])

const start = computed(() => toDate(props.booking.starts_at))
const end = computed(() => toDate(props.booking.ends_at))
const minutes = computed(() => Math.round((end.value - start.value) / 60000))

const month = computed(() =>
  start.value.toLocaleDateString('es', { month: 'short' }).replace('.', ''),
)
const day = computed(() => start.value.getDate())

const state = computed(() => {
  if (props.booking.status === 'cancelled') return { label: 'Cancelada', tone: 'badge-danger' }

  const now = Date.now()

  if (end.value.getTime() <= now) return { label: 'Finalizada', tone: '' }
  if (start.value.getTime() <= now) return { label: 'En curso', tone: 'badge-warn' }

  return { label: 'Confirmada', tone: 'badge-ok' }
})

// El botón solo aparece si cancelar tiene sentido (el servidor vuelve a comprobarlo)
const canCancel = computed(() => {
  if (props.booking.status !== 'confirmed') return false
  if (end.value.getTime() <= Date.now()) return false

  return props.allowStarted || start.value.getTime() > Date.now()
})
</script>

<template>
  <article class="booking-item" :class="{ cancelled: booking.status === 'cancelled' }">
    <div class="booking-date">
      <span class="month">{{ month }}</span>
      <span class="day">{{ day }}</span>
    </div>

    <div class="booking-main">
      <div class="booking-title" :title="booking.title">{{ booking.title }}</div>

      <div class="booking-meta">
        <span>{{ booking.room?.name }}</span>
        <span>
          <AppIcon name="clock" :size="14" />
          {{ formatShortDate(booking.starts_at) }} · {{ formatTime(booking.starts_at) }} –
          {{ formatTime(booking.ends_at) }} ({{ formatDuration(minutes) }})
        </span>
        <span v-if="showUser && booking.user">
          <AppIcon name="users" :size="14" />
          {{ booking.user.name }}
        </span>
      </div>
    </div>

    <div class="booking-side">
      <span class="badge" :class="state.tone">{{ state.label }}</span>
      <button v-if="canCancel" type="button" class="secondary" @click="emit('cancel', booking)">
        Cancelar
      </button>
    </div>
  </article>
</template>
