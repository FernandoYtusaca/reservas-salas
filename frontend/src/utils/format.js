export function formatDuration(minutes) {
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  if (!hours) return `${rest} min`

  return rest ? `${hours} h ${rest} min` : `${hours} h`
}

const pad = (n) => String(n).padStart(2, '0')

// Acepta un instante ISO o milisegundos. Se muestra en la hora del navegador.
export function formatTime(value) {
  return new Date(value).toLocaleTimeString('es', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  })
}

// 'YYYY-MM-DD' con la fecha local (toISOString usaría UTC y a ciertas horas daría otro día)
export function toLocalDate(date = new Date()) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function addDays(dateString, days) {
  const [y, m, d] = dateString.split('-').map(Number)

  return toLocalDate(new Date(y, m - 1, d + days))
}

export function formatLongDate(dateString) {
  const [y, m, d] = dateString.split('-').map(Number)

  return new Date(y, m - 1, d).toLocaleDateString('es', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
}
