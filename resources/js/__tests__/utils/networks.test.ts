import type { NetworkData } from '@/types'
import { isBuiltInNetwork, isIdleNetwork, networkStatus } from '@/utils/networks'
import { expect, test } from 'vite-plus/test'

const network = (overrides: Partial<NetworkData>): NetworkData => ({
  name: 'hello',
  quadlet: null,
  driver: 'bridge',
  subnet: null,
  gateway: null,
  dns: true,
  internal: false,
  ipv6: false,
  containers: 0,
  applications: [],
  default: false,
  ...overrides,
})

test('counts the default network and the host and none drivers as built in, and never idle', () => {
  expect(
    [network({ default: true }), network({ driver: 'host' }), network({ driver: 'none' })].every(isBuiltInNetwork),
  ).toBe(true)
  expect(isIdleNetwork(network({ driver: 'host' }))).toBe(false)
  expect([isIdleNetwork(network({})), isIdleNetwork(network({ containers: 2 }))]).toEqual([true, false])
})

test('labels a network by whether it is the default, built in, active or idle', () => {
  expect(
    [network({ default: true }), network({ driver: 'none' }), network({ containers: 1 }), network({})].map(
      (item) => networkStatus(item).label,
    ),
  ).toEqual(['Default', 'Built-in', 'Active', 'Idle'])
})
