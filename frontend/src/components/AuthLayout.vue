<template>
  <main class="auth-shell">
    <section class="auth-hero">
      <div class="brand brand-light">
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
      </div>

      <h2 class="hero-title">Reserva tu sala en segundos</h2>
      <p class="hero-text">
        Consulta qué franjas están libres, reserva sin solapamientos y gestiona tus reservas desde
        un solo lugar.
      </p>

      <ul class="hero-points">
        <li>Disponibilidad al instante</li>
        <li>Sin dobles reservas</li>
        <li>Cancelación sencilla</li>
      </ul>
    </section>

    <section class="auth-panel">
      <slot />
    </section>
  </main>
</template>
