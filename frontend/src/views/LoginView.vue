<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AuthLayout from '@/components/AuthLayout.vue'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const message = ref('')
const loading = ref(false)

async function submit() {
  errors.value = {}
  message.value = ''
  loading.value = true
  try {
    await auth.login(form)
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
      <h1>Iniciar sesión</h1>

      <label>
        Correo
        <input v-model="form.email" type="email" required autocomplete="email" />
        <small v-if="errors.email" class="error">{{ errors.email[0] }}</small>
      </label>

      <label>
        Contraseña
        <input v-model="form.password" type="password" required autocomplete="current-password" />
        <small v-if="errors.password" class="error">{{ errors.password[0] }}</small>
      </label>

      <p v-if="message" class="error">{{ message }}</p>

      <button :disabled="loading">{{ loading ? 'Entrando...' : 'Entrar' }}</button>

      <p>¿No tienes cuenta? <RouterLink to="/register">Regístrate</RouterLink></p>
    </form>
  </AuthLayout>
</template>
