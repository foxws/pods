import type { NetworkData } from '@/types'
import type { BadgeProps } from '@nuxt/ui'

/**
 * Podman's default network, and the host and none drivers: always there, and never removed.
 */
export function isBuiltInNetwork(network: NetworkData): boolean {
  return network.default || network.driver === 'host' || network.driver === 'none'
}

/**
 * A network of its own that no container is on, which pruning would remove.
 */
export function isIdleNetwork(network: NetworkData): boolean {
  return !isBuiltInNetwork(network) && network.containers === 0
}

export function networkStatus(network: NetworkData): { label: string; color: BadgeProps['color'] } {
  if (network.default) {
    return { label: 'Default', color: 'neutral' }
  }

  if (isBuiltInNetwork(network)) {
    return { label: 'Built-in', color: 'neutral' }
  }

  return network.containers ? { label: 'Active', color: 'success' } : { label: 'Idle', color: 'warning' }
}
