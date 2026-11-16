import type { ImageData } from '@/types'
import { applicationsWithUpdates, totalSize, unusedImages } from '@/utils/images'
import { expect, test } from 'vite-plus/test'

const image = (overrides: Partial<ImageData>): ImageData => ({
  id: 'a3f1c92e5b7d',
  repository: 'docker.io/library/app',
  tag: 'latest',
  size: 1024,
  created_at: null,
  used_by: ['app'],
  containers: 1,
  dangling: false,
  auto_update: 'registry',
  update: null,
  ...overrides,
})

test('counts dangling images and images no container uses as unused', () => {
  const dangling = image({ id: 'dangling', dangling: true, size: 2048 })
  const unused = image({ id: 'unused', containers: 0, used_by: [], size: null })

  const images = unusedImages([image({ id: 'used' }), dangling, unused])

  expect(images).toEqual([dangling, unused])
  expect(totalSize(images)).toBe(2048)
})

test('lists each application with an image update once, sorted', () => {
  expect(
    applicationsWithUpdates([
      image({ used_by: ['paperless', 'immich'], update: '8-alpine' }),
      image({ used_by: ['immich'], update: 'v2.3.1' }),
      image({ used_by: ['vaultwarden'] }),
    ]),
  ).toEqual(['immich', 'paperless'])
})
