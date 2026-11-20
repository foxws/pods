import type { PodData } from '@/types'
import type { BadgeProps } from '@nuxt/ui'

export function isStoppedPod(pod: PodData): boolean {
  return ['exited', 'stopped', 'created', 'dead'].includes(pod.status)
}

const statuses: Record<string, { label: string; color: BadgeProps['color'] }> = {
  running: { label: 'Running', color: 'success' },
  degraded: { label: 'Degraded', color: 'warning' },
  paused: { label: 'Paused', color: 'warning' },
  dead: { label: 'Dead', color: 'error' },
  exited: { label: 'Exited', color: 'neutral' },
  stopped: { label: 'Stopped', color: 'neutral' },
  created: { label: 'Created', color: 'neutral' },
}

export function podStatus(pod: PodData): { label: string; color: BadgeProps['color'] } {
  return statuses[pod.status] ?? { label: 'Unknown', color: 'warning' }
}

/**
 * "2 of 3" while the pod runs, so a degraded pod shows how many of its containers are up.
 */
export function podContainerCount(pod: PodData): string {
  return pod.status === 'running' || pod.status === 'degraded'
    ? `${pod.running} of ${pod.containers}`
    : String(pod.containers)
}
