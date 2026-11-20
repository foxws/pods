import type { BadgeProps } from '@nuxt/ui'
import type { ImageData } from '@/types'

/**
 * Images that pruning would remove: dangling layers and tagged images no container uses.
 */
export function unusedImages(images: ImageData[]): ImageData[] {
  return images.filter((image) => image.dangling || image.containers === 0)
}

export function totalSize(images: ImageData[]): number {
  return images.reduce((total, image) => total + (image.size ?? 0), 0)
}

/**
 * The applications whose images podman auto-update would pull a newer digest for.
 */
export function applicationsWithUpdates(images: ImageData[]): string[] {
  return [...new Set(images.filter((image) => image.update !== null).flatMap((image) => image.used_by))].sort()
}

/**
 * The image's name without its registry and namespace, such as "immich-server".
 */
export function imageName(image: ImageData): string {
  return image.repository?.split('/').pop() ?? '<none>'
}

/**
 * Where the image comes from, such as "ghcr.io/immich-app", or that it's an untagged layer.
 */
export function imageRegistry(image: ImageData): string {
  return image.repository ? image.repository.split('/').slice(0, -1).join('/') : 'untagged layer'
}

/**
 * A tagged image no container uses (a dangling layer has a badge of its own).
 */
export function isUnusedTagged(image: ImageData): boolean {
  return !image.dangling && image.containers === 0
}

export function imageStatus(image: ImageData): { label: string; color: BadgeProps['color'] } {
  if (image.dangling) {
    return { label: 'Dangling', color: 'warning' }
  }

  if (image.update) {
    return { label: 'Update available', color: 'info' }
  }

  return image.containers ? { label: 'In use', color: 'success' } : { label: 'Unused', color: 'neutral' }
}
