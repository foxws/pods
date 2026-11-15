const units = ['B', 'KB', 'MB', 'GB', 'TB']

export function formatBytes(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined) {
    return '–'
  }

  let value = bytes
  let unit = 0

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }

  return `${value >= 10 || unit === 0 ? Math.round(value) : value.toFixed(1)} ${units[unit]}`
}

export function formatRelative(date: string | null | undefined): string {
  if (!date) {
    return '–'
  }

  const seconds = Math.round((new Date(date).getTime() - Date.now()) / 1000)
  const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })
  const steps: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ]

  for (const [unit, size] of steps) {
    if (Math.abs(seconds) >= size) {
      return formatter.format(Math.round(seconds / size), unit)
    }
  }

  return formatter.format(seconds, 'second')
}

export function formatPort(port: string): string {
  return port.replace(/^0\.0\.0\.0:/, '').replace(/->.*$/, '')
}
