import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token'))
  const user = ref(null)

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  function setSession(data) {
    token.value = data.token
    user.value = data.user
    localStorage.setItem('token', data.token)
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem('token')
  }

  async function register(form) {
    setSession(await api.post('/register', form))
  }

  async function login(form) {
    setSession(await api.post('/login', form))
  }

  async function fetchUser() {
    user.value = await api.get('/user')
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      clearSession()
    }
  }

  return { token, user, isAuthenticated, isAdmin, register, login, fetchUser, logout }
})
