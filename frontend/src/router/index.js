import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: { name: 'rooms' } },
    {
      path: '/rooms',
      name: 'rooms',
      component: () => import('@/views/RoomsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/rooms/:id',
      name: 'room-detail',
      component: () => import('@/views/RoomDetailView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/my-bookings',
      name: 'my-bookings',
      component: () => import('@/views/MyBookingsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('@/views/AdminView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guestOnly: true },
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'rooms' } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) return { name: 'login' }
  if (to.meta.guestOnly && auth.isAuthenticated) return { name: 'rooms' }

  if (to.meta.requiresAdmin) {
    // Tras recargar solo persiste el token: el rol se consulta al servidor
    if (!auth.user) {
      try {
        await auth.fetchUser()
      } catch {
        return { name: 'login' }
      }
    }

    if (!auth.isAdmin) return { name: 'rooms' }
  }
})

export default router
