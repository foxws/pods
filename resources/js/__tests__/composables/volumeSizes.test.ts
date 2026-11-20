import { useVolumeSizes } from '@/composables/volumeSizes'
import type { HostProperty, HostVolumeData } from '@/types'
import { describe, expect, it } from 'vite-plus/test'
import { ref } from 'vue'

const volume = (name: string, type = 'named', size: number | null = null): HostVolumeData => ({
  name,
  quadlet: null,
  driver: 'local',
  mountpoint: null,
  size,
  type,
  containers: [],
  applications: [],
})

describe('useVolumeSizes', () => {
  it('waits for the sizes of measured volumes only, then takes them by name', () => {
    const sizes = ref<HostProperty<Record<string, number | null>> | undefined>(undefined)
    const { sizeOf, isSizing } = useVolumeSizes(sizes)

    expect(isSizing(volume('immich-library'))).toBe(true)
    expect(isSizing(volume('/var/mnt/photos', 'bind'))).toBe(false)

    sizes.value = { data: { 'immich-library': 2048 }, error: null }

    expect(isSizing(volume('immich-library'))).toBe(false)
    expect(sizeOf(volume('immich-library'))).toBe(2048)
    expect(sizeOf(volume('vaultwarden-data'))).toBeNull()
  })

  it('prefers a size lpod already gave with the volume', () => {
    const { sizeOf } = useVolumeSizes({ data: { 'immich-library': 2048 }, error: null })

    expect(sizeOf(volume('immich-library', 'named', 4096))).toBe(4096)
  })

  it('stops waiting when the sizes failed', () => {
    const { sizeOf, isSizing } = useVolumeSizes({ data: null, error: 'du: cannot access' })

    expect(isSizing(volume('immich-library'))).toBe(false)
    expect(sizeOf(volume('immich-library'))).toBeNull()
  })
})
