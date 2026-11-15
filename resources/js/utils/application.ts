import type { ApplicationData } from '@/types'
import { formatBytes } from '@/utils/format'

export function pluralize(count: number, noun: string): string {
  return `${count} ${noun}${count === 1 ? '' : 's'}`
}

/**
 * The quadlet file an application was generated from, e.g. `immich-pod.service` → `immich.pod`.
 */
export function quadletName(application: Pick<ApplicationData, 'unit' | 'kind'>): string {
  return `${application.unit.replace(/(-pod)?\.service$/, '')}.${application.kind}`
}

export function describeUnits(application: Pick<ApplicationData, 'pods' | 'containers'>): string {
  return [application.pods ? pluralize(application.pods, 'pod') : null, pluralize(application.containers, 'container')]
    .filter(Boolean)
    .join(' · ')
}

export function describeApplication(application: ApplicationData): string {
  return [
    quadletName(application),
    pluralize(application.containers, 'container'),
    application.memory ? formatBytes(application.memory) : null,
  ]
    .filter(Boolean)
    .join(' · ')
}
