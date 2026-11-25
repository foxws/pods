import type { UnitStatus } from '@/types'
import type { BadgeProps } from '@nuxt/ui'

type StatusMeta = {
  color: BadgeProps['color']
  icon: string
}

const statuses: Record<UnitStatus, StatusMeta> = {
  running: { color: 'success', icon: 'i-lucide-circle-check' },
  activating: { color: 'info', icon: 'i-lucide-loader-circle' },
  deactivating: { color: 'info', icon: 'i-lucide-loader-circle' },
  reloading: { color: 'info', icon: 'i-lucide-loader-circle' },
  failed: { color: 'error', icon: 'i-lucide-circle-x' },
  inactive: { color: 'neutral', icon: 'i-lucide-circle-stop' },
  unknown: { color: 'warning', icon: 'i-lucide-circle-help' },
}

export function statusMeta(status: UnitStatus): StatusMeta {
  return statuses[status] ?? statuses.unknown
}

export function isTransitioning(status: UnitStatus): boolean {
  return status === 'activating' || status === 'deactivating' || status === 'reloading'
}
