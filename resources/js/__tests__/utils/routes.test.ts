import type { ReachableUpstreamData } from '@/types'
import { reachableUpstream } from '@/utils/routes'
import { expect, test } from 'vite-plus/test'

const reachable = (overrides: Partial<ReachableUpstreamData>): ReachableUpstreamData => ({
  container: 'systemd-hello',
  reach: 'network',
  name: 'systemd-hello',
  port: 8000,
  published: '127.0.0.1:8000->8000/tcp',
  ...overrides,
})

test('offers a container by name, ending in a colon when its port is unknown', () => {
  expect(reachableUpstream(reachable({}))).toBe('systemd-hello:8000')
  expect(reachableUpstream(reachable({ port: null, published: null }))).toBe('systemd-hello:')
  expect(reachableUpstream(reachable({ reach: 'host', name: 'host.containers.internal', port: 3000 }))).toBe(
    'host.containers.internal:3000',
  )
})
