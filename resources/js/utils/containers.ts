import type { ContainerStatsData, HostContainerData } from '@/types'
import type { BadgeProps } from '@nuxt/ui'

type ContainerState = {
  label: string
  color: BadgeProps['color']
  busy?: boolean
}

export function containerState(container: Pick<HostContainerData, 'state' | 'exit_code'>): ContainerState {
  switch (container.state) {
    case 'running':
      return { label: 'Running', color: 'success' }
    case 'starting':
    case 'stopping':
    case 'restarting':
      return { label: container.state[0]?.toUpperCase() + container.state.slice(1), color: 'info', busy: true }
    case 'paused':
      return { label: 'Paused', color: 'warning' }
    case 'exited':
      return container.exit_code
        ? { label: `Exited ${container.exit_code}`, color: 'error' }
        : { label: 'Exited', color: 'neutral' }
    case 'created':
      return { label: 'Created', color: 'neutral' }
    case 'stopped':
      return { label: 'Stopped', color: 'neutral' }
    default:
      return { label: 'Unknown', color: 'warning' }
  }
}

/**
 * A short image name for tables, e.g. `ghcr.io/immich-app/immich-server:v2.3.1` → `immich-server:v2.3.1`.
 */
export function shortImage(image: string | null | undefined): string {
  return image?.split('/').pop() ?? '–'
}

export type ContainerCounter = 'net_in' | 'net_out' | 'block_in' | 'block_out'

/**
 * Bytes per second of a counter between two stats samples, or null without two samples that have
 * it. A counter that went down (the container restarted) counts as 0.
 */
export function ratePerSecond(
  current: ContainerStatsData | null | undefined,
  previous: ContainerStatsData | null | undefined,
  counter: ContainerCounter,
): number | null {
  const now = current?.[counter]
  const before = previous?.[counter]

  if (!current || !previous || now === null || now === undefined || before === null || before === undefined) {
    return null
  }

  const seconds = (Date.parse(current.sampled_at ?? '') - Date.parse(previous.sampled_at ?? '')) / 1000

  return seconds > 0 ? Math.max(0, (now - before) / seconds) : null
}
