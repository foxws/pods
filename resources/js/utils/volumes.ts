import type { HostVolumeData } from '@/types'

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
