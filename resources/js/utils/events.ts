import type { HostEventData } from '@/types'

export type EventView = { subject: string; summary: string; icon: string; color: string }

/**
 * A line for the activity feed: who it happened to, what happened, and an icon in a fitting color.
 */
export function describeEvent(event: HostEventData): EventView {
  const subject = event.type === 'image' ? event.name : (event.application ?? event.name)
  const container = event.application && event.application !== event.name ? ` (${event.name})` : ''

  if (event.type === 'image') {
    return event.action === 'pull'
      ? { subject, summary: 'pulled', icon: 'i-lucide-download', color: 'text-info' }
      : { subject, summary: 'removed', icon: 'i-lucide-trash-2', color: 'text-muted' }
  }

  switch (event.action) {
    case 'start':
      return { subject, summary: `started${container}`, icon: 'i-lucide-play', color: 'text-success' }
    case 'restart':
      return { subject, summary: `restarted${container}`, icon: 'i-lucide-rotate-cw', color: 'text-success' }
    case 'stop':
      return { subject, summary: `stopped${container}`, icon: 'i-lucide-square', color: 'text-muted' }
    case 'died':
      return event.exit_code
        ? {
            subject,
            summary: `exited with code ${event.exit_code}${container}`,
            icon: 'i-lucide-circle-x',
            color: 'text-error',
          }
        : { subject, summary: `exited${container}`, icon: 'i-lucide-circle-stop', color: 'text-muted' }
    default:
      return { subject, summary: `was removed${container}`, icon: 'i-lucide-trash-2', color: 'text-muted' }
  }
}

/**
 * A time of day for today's events, otherwise "Yesterday" or the date.
 */
export function eventTime(time: string | null, now: Date = new Date()): string {
  if (!time) {
    return '–'
  }

  const date = new Date(time)
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())

  if (date >= startOfToday) {
    return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
  }

  if (date >= new Date(startOfToday.getTime() - 86_400_000)) {
    return 'Yesterday'
  }

  return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}
