import type { HostVolumeData } from '@/types'
import { displayName, isInPodmanStorage, volumeBadge } from '@/utils/volumes'
import { describe, expect, it } from 'vite-plus/test'

const volume = (type: string, name = 'immich-library'): HostVolumeData => ({
  name,
  quadlet: null,
  driver: 'local',
  mountpoint: null,
  size: null,
  type,
  containers: [],
  applications: [],
})

describe('isInPodmanStorage', () => {
  it('is true for named and anonymous volumes only', () => {
    expect(['named', 'anonymous', 'device', 'nfs', 'bind'].filter((type) => isInPodmanStorage(volume(type)))).toEqual([
      'named',
      'anonymous',
    ])
  })
})

describe('displayName', () => {
  it('shortens the ID of an anonymous volume, and keeps other names', () => {
    expect(displayName(volume('anonymous', '4f2a91c0e7b35d6a8c1e2f3b4a5d6c7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c'))).toBe(
      '4f2a91c0e7b3…',
    )
    expect(displayName(volume('named'))).toBe('immich-library')
  })
})

describe('volumeBadge', () => {
  it('says a volume is unused before what type it is', () => {
    expect(volumeBadge(volume('nfs')).label).toBe('Unused')
    expect(volumeBadge({ ...volume('nfs'), containers: ['jellyfin'] }).label).toBe('NFS')
    expect(volumeBadge({ ...volume('something-new'), containers: ['jellyfin'] }).label).toBe('Named')
  })
})
