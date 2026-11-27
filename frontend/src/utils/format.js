export function formatDuration(minutes) {
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  if (!hours) return `${rest} min`

  return rest ? `${hours} h ${rest} min` : `${hours} h`
}
