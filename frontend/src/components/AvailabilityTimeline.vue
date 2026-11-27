<script setup>
import { computed } from 'vue'
import { formatTime } from '@/utils/format'

const props = defineProps({
  availability: { type: Object, required: true },
  start: { type: Number, default: null },
  end: { type: Number, default: null },
})
const emit = defineEmits(['pick'])

const STEP = 15 * 60 * 1000
const HOUR = 60 * 60 * 1000

const opens = computed(() => new Date(props.availability.opens_at).getTime())
const closes = computed(() => new Date(props.availability.closes_at).getTime())

// Convierte un instante en un porcentaje del ancho de la barra (0 = apertura, 100 = cierre)
function percent(ms) {
  const ratio = (ms - opens.value) / (closes.value - opens.value)

  return Math.min(Math.max(ratio, 0), 1) * 100
}

function position(startMs, endMs) {
  const left = percent(startMs)

  return { left: `${left}%`, width: `${percent(endMs) - left}%` }
}

const busy = computed(() =>
  props.availability.bookings.map((booking) => {
    const start = new Date(booking.starts_at).getTime()
    const end = new Date(booking.ends_at).getTime()

    return {
      id: booking.id,
      mine: booking.mine,
      name: booking.title || 'Ocupado',
      label: `${formatTime(start)} – ${formatTime(end)}`,
      style: position(start, end),
    }
  }),
)

const free = computed(() =>
  props.availability.free.map((range) => {
    const start = new Date(range.starts_at).getTime()
    const end = new Date(range.ends_at).getTime()

    return {
      key: range.starts_at,
      start,
      end,
      label: `${formatTime(start)} – ${formatTime(end)}`,
      style: position(start, end),
    }
  }),
)

const selection = computed(() =>
  props.start && props.end ? position(props.start, props.end) : null,
)

// Una marca por cada hora en punto entre la apertura y el cierre
const ticks = computed(() => {
  const list = []

  for (let t = Math.ceil(opens.value / HOUR) * HOUR; t <= closes.value; t += HOUR) {
    list.push({ ms: t, left: `${percent(t)}%`, label: formatTime(t) })
  }

  return list
})

// Al pulsar un tramo libre se calcula en qué punto se hizo clic y se redondea
// hacia abajo a un múltiplo de 15 minutos dentro de ese tramo
function pickAt(range, event) {
  const box = event.currentTarget.getBoundingClientRect()
  const ratio = (event.clientX - box.left) / box.width

  let moment = range.start + ratio * (range.end - range.start)
  moment = Math.floor(moment / STEP) * STEP
  moment = Math.min(Math.max(moment, range.start), range.end - STEP)

  emit('pick', moment)
}
</script>

<template>
  <div class="timeline">
    <div class="timeline-bar">
      <div
        v-for="b in busy"
        :key="b.id"
        class="seg busy"
        :class="{ mine: b.mine }"
        :style="b.style"
        :title="`${b.name} (${b.label})`"
      >
        <span>{{ b.name }}</span>
      </div>

      <button
        v-for="r in free"
        :key="r.key"
        type="button"
        class="seg free"
        :style="r.style"
        :title="`Libre ${r.label}`"
        :aria-label="`Franja libre ${r.label}`"
        @click="pickAt(r, $event)"
      />

      <div v-if="selection" class="seg selection" :style="selection" />
    </div>

    <div class="timeline-axis">
      <span v-for="t in ticks" :key="t.ms" class="tick" :style="{ left: t.left }">
        {{ t.label }}
      </span>
    </div>

    <div class="free-list">
      <template v-if="free.length">
        <span class="muted">Franjas libres:</span>
        <button
          v-for="r in free"
          :key="r.key"
          type="button"
          class="chip"
          @click="emit('pick', r.start)"
        >
          {{ r.label }}
        </button>
      </template>
      <span v-else class="muted">No quedan franjas libres este día.</span>
    </div>

    <div class="legend">
      <span><i class="lg-free" />Libre</span>
      <span><i class="lg-busy" />Ocupado</span>
      <span><i class="lg-mine" />Tuya</span>
      <span><i class="lg-selection" />Tu selección</span>
    </div>
  </div>
</template>
