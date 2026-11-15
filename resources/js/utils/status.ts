import type { UnitStatus } from '@/types'
import type { BadgeProps } from '@nuxt/ui'

type StatusMeta = {
  label: string
  color: BadgeProps['color']
  icon: string
}

const statuses: Record<UnitStatus, StatusMeta> = {
  running: { label: 'Running', color: 'success', icon: 'i-lucide-circle-check' },
  activating: { label: 'Starting', color: 'info', icon: 'i-lucide-loader-circle' },
  deactivating: { label: 'Stopping', color: 'info', icon: 'i-lucide-loader-circle' },
  reloading: { label: 'Reloading', color: 'info', icon: 'i-lucide-loader-circle' },
  failed: { label: 'Failed', color: 'error', icon: 'i-lucide-circle-x' },
  inactive: { label: 'Stopped', color: 'neutral', icon: 'i-lucide-circle-stop' },
  unknown: { label: 'Unknown', color: 'warning', icon: 'i-lucide-circle-help' },
}

export function statusMeta(status: UnitStatus): StatusMeta {
  return statuses[status] ?? statuses.unknown
}

export function isTransitioning(status: UnitStatus): boolean {
  return status === 'activating' || status === 'deactivating' || status === 'reloading'
}
