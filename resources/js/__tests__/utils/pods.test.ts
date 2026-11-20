import type { PodData } from '@/types'
import { isStoppedPod, podContainerCount, podStatus } from '@/utils/pods'
import { expect, test } from 'vite-plus/test'

const pod = (overrides: Partial<PodData>): PodData => ({
  id: 'b7c1',
  name: 'immich',
  status: 'running',
  application: 'immich',
  quadlet: 'immich.pod',
  unit: 'immich-pod.service',
  containers: 3,
  running: 2,
  ports: [],
  networks: [],
  created_at: null,
  ...overrides,
})

test('counts exited, stopped, created and dead pods as stopped', () => {
  expect(
    ['exited', 'stopped', 'created', 'dead', 'running', 'degraded'].filter((status) => isStoppedPod(pod({ status }))),
  ).toEqual(['exited', 'stopped', 'created', 'dead'])
})

test('shows how many containers run while a pod is up', () => {
  expect(podContainerCount(pod({ status: 'degraded' }))).toBe('2 of 3')
  expect(podContainerCount(pod({ status: 'exited' }))).toBe('3')
})

test('labels a status podman reports, and an unknown one', () => {
  expect([podStatus(pod({ status: 'degraded' })).label, podStatus(pod({ status: 'weird' })).label]).toEqual([
    'Degraded',
    'Unknown',
  ])
})
