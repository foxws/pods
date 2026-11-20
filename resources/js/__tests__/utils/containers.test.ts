import type { ContainerStatsData } from '@/types'
import { ratePerSecond } from '@/utils/containers'
import { expect, test } from 'vite-plus/test'

const sample = (net_in: number | null, sampled_at: string | null): ContainerStatsData => ({
  cpu: null,
  memory: null,
  memory_limit: null,
  net_in,
  net_out: null,
  block_in: null,
  block_out: null,
  pids: null,
  sampled_at,
})

test('works out bytes per second between two samples', () => {
  expect(ratePerSecond(sample(15_000, '2026-11-20T10:00:05Z'), sample(5_000, '2026-11-20T10:00:00Z'), 'net_in')).toBe(
    2_000,
  )
})

test('counts a counter that went down, after a restart, as 0', () => {
  expect(ratePerSecond(sample(100, '2026-11-20T10:00:05Z'), sample(5_000, '2026-11-20T10:00:00Z'), 'net_in')).toBe(0)
})

test('has no rate without two samples that have the counter and time apart', () => {
  expect(ratePerSecond(sample(100, '2026-11-20T10:00:05Z'), null, 'net_in')).toBeNull()
  expect(ratePerSecond(sample(100, '2026-11-20T10:00:05Z'), sample(null, '2026-11-20T10:00:00Z'), 'net_in')).toBeNull()
  expect(ratePerSecond(sample(100, '2026-11-20T10:00:05Z'), sample(50, '2026-11-20T10:00:05Z'), 'net_in')).toBeNull()
})
