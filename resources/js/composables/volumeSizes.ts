import type { HostProperty, HostVolumeData } from '@/types'
import { isInPodmanStorage } from '@/utils/volumes'
import { toValue, type MaybeRefOrGetter } from 'vue'

/**
 * The Volumes page's sizes, which load after the list (lpod walks every file for them), merged into
 * its rows by name.
 */
export function useVolumeSizes(sizes: MaybeRefOrGetter<HostProperty<Record<string, number | null>> | undefined>) {
  const sizeOf = (volume: HostVolumeData): number | null => volume.size ?? toValue(sizes)?.data?.[volume.name] ?? null

  /** Whether the volume's size is still loading; volumes lpod doesn't measure never are. */
  const isSizing = (volume: HostVolumeData): boolean => toValue(sizes) === undefined && isInPodmanStorage(volume)

  return { sizeOf, isSizing }
}
