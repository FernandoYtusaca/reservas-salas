export function formatDuration(minutes) {
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  if (!hours) return `${rest} min`

  return rest ? `${hours} h ${rest} min` : `${hours} h`
}

const pad = (n) => String(n).padStart(2, '0')

export function toDate(value) {
  if (typeof value === 'number') return new Date(value)
  return new Date(String(value).replace(/(\.\d{3})\d+/, '$1'))
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

export function formatTime(value) {
  return toDate(value).toLocaleTimeString('es', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  })
}

export function formatShortDate(value) {
  return toDate(value).toLocaleDateString('es', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  })
}
