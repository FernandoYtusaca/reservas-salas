<script setup>
import { onMounted, onUnmounted } from 'vue'

defineProps({
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirmar' },
  cancelLabel: { type: String, default: 'Volver' },
  danger: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})
const emit = defineEmits(['confirm', 'cancel'])

function onKey(event) {
  if (event.key === 'Escape') emit('cancel')
}

onMounted(() => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div class="modal-backdrop" @click.self="emit('cancel')">
      <div class="dialog" role="dialog" aria-modal="true" :aria-label="title">
        <h2>{{ title }}</h2>
        <p v-if="message" class="muted">{{ message }}</p>
        <p v-if="error" class="error">{{ error }}</p>

        <div class="form-actions">
          <button type="button" :class="{ danger }" :disabled="loading" @click="emit('confirm')">
            {{ loading ? 'Procesando...' : confirmLabel }}
          </button>
          <button type="button" class="secondary" :disabled="loading" @click="emit('cancel')">
            {{ cancelLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
