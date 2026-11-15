import type { HostContainerData } from '@/types'
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
