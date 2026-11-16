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
