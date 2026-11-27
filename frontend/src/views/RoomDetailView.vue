<script setup>
import AppHeader from '@/components/AppHeader.vue'
</script>

<template>
  <main class="page">
    <AppHeader />

    <RouterLink :to="{ name: 'rooms' }" class="back">← Volver a las salas</RouterLink>

    <section class="panel">
      <h2>Sala</h2>
      <p class="muted">Próximamente: disponibilidad y reservas.</p>
    </section>
  </main>
</template>
