<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const initials = computed(() =>
  (auth.user?.name || '')
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .slice(0, 2)
    .join('')
    .toUpperCase(),
)

onMounted(() => {
  if (!auth.user) auth.fetchUser()
})

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="topbar">
    <div class="topbar-left">
      <RouterLink to="/" class="brand">
        <span class="brand-mark">
          <svg
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
        </span>
        <span class="brand-name">Reserva de Salas</span>
      </RouterLink>

      <nav class="nav">
        <RouterLink :to="{ name: 'rooms' }">Salas</RouterLink>
        <RouterLink :to="{ name: 'my-bookings' }">Mis reservas</RouterLink>
        <RouterLink v-if="auth.isAdmin" :to="{ name: 'admin' }">Administración</RouterLink>
      </nav>
    </div>

    <div class="user">
      <template v-if="auth.user">
        <span class="avatar">{{ initials }}</span>
        <span class="user-name">{{ auth.user.name }}</span>
        <span v-if="auth.isAdmin" class="tag-admin">Admin</span>
      </template>
      <button class="secondary" @click="logout">Salir</button>
    </div>
  </header>
</template>
