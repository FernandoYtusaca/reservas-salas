<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AuthLayout from '@/components/AuthLayout.vue'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref({})
const message = ref('')
const loading = ref(false)

async function submit() {
  errors.value = {}
  message.value = ''
  loading.value = true
  try {
    await auth.register(form)
    router.push({ name: 'rooms' })
  } catch (e) {
    if (e.status === 422) errors.value = e.errors
    else message.value = 'No se pudo conectar con el servidor.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <form class="card" @submit.prevent="submit">
      <h1>Crear cuenta</h1>

      <label>
        Nombre
        <input v-model="form.name" type="text" required autocomplete="name" />
        <small v-if="errors.name" class="error">{{ errors.name[0] }}</small>
      </label>

      <label>
        Correo
        <input v-model="form.email" type="email" required autocomplete="email" />
        <small v-if="errors.email" class="error">{{ errors.email[0] }}</small>
      </label>

      <label>
        Contraseña (mínimo 8 caracteres)
        <input v-model="form.password" type="password" required autocomplete="new-password" />
        <small v-if="errors.password" class="error">{{ errors.password[0] }}</small>
      </label>

      <label>
        Confirmar contraseña
        <input
          v-model="form.password_confirmation"
          type="password"
          required
          autocomplete="new-password"
        />
      </label>

      <p v-if="message" class="error">{{ message }}</p>

      <button :disabled="loading">{{ loading ? 'Creando...' : 'Registrarme' }}</button>

      <p>¿Ya tienes cuenta? <RouterLink to="/login">Inicia sesión</RouterLink></p>
    </form>
  </AuthLayout>
</template>
