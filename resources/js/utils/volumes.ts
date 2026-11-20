import type { HostVolumeData } from '@/types'
import type { BadgeProps } from '@nuxt/ui'

/**
 * Whether Podman keeps the volume's data in its own storage (a named or anonymous volume), so lpod
 * can export it and measure its size. One backed by a folder, a device or an NFS share, or a bind
 * mount, can be a whole media library, or remote. Mirrors HostVolumeData::isExportable().
 */
export function isInPodmanStorage(volume: HostVolumeData): boolean {
  return volume.type === 'named' || volume.type === 'anonymous'
}

/**
 * An anonymous volume's name is a 64-character ID, so it's shortened like an image ID.
 */
export function displayName(volume: HostVolumeData): string {
  return volume.type === 'anonymous' ? `${volume.name.slice(0, 12)}…` : volume.name
}

export function isUnusedVolume(volume: HostVolumeData): boolean {
  return volume.containers.length === 0
}

const types: Record<string, { label: string; color: BadgeProps['color'] }> = {
  named: { label: 'Named', color: 'neutral' },
  bind: { label: 'Bind', color: 'info' },
  device: { label: 'Host folder', color: 'info' },
  nfs: { label: 'NFS', color: 'primary' },
  anonymous: { label: 'Anonymous', color: 'warning' },
}

/**
 * The volume's badge: unused before its type, since that's what pruning acts on.
 */
export function volumeBadge(volume: HostVolumeData): { label: string; color: BadgeProps['color'] } {
  if (isUnusedVolume(volume)) {
    return { label: 'Unused', color: 'warning' }
  }

  return types[volume.type] ?? { label: 'Named', color: 'neutral' }
}
