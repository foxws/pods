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

/**
 * A CPU share as podman reports it, e.g. 3.3117374108025945 → "3.3%".
 */
export function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return '–'
  }

  return `${value < 10 ? value.toFixed(1) : Math.round(value)}%`
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

export function formatUptime(since: string | null | undefined): string {
  if (!since) {
    return '–'
  }

  const minutes = Math.max(0, Math.floor((Date.now() - new Date(since).getTime()) / 60000))
  const days = Math.floor(minutes / 1440)
  const hours = Math.floor((minutes % 1440) / 60)

  if (days > 0) {
    return `${days} ${days === 1 ? 'day' : 'days'}`
  }

  if (hours > 0) {
    return `${hours} h ${minutes % 60} min`
  }

  return `${minutes} min`
}

/**
 * How much of the total is used, as a whole percentage (0 without a total).
 */
export function percentOf(used: number, total: number): number {
  return total > 0 ? Math.round((used / total) * 100) : 0
}

/**
 * A rate in bytes per second, such as "1.2 MB/s", or "–" without one.
 */
export function formatRate(bytesPerSecond: number | null): string {
  return bytesPerSecond === null ? '–' : `${formatBytes(bytesPerSecond)}/s`
}
