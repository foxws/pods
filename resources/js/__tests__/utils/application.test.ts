import { describeUnits, quadletName } from '@/utils/application'
import { expect, test } from 'vite-plus/test'

test('derives the quadlet file from a pod unit', () => {
  expect(quadletName({ unit: 'immich-pod.service', kind: 'pod' })).toBe('immich.pod')
})

test('derives the quadlet file from a container unit', () => {
  expect(quadletName({ unit: 'vaultwarden.service', kind: 'container' })).toBe('vaultwarden.container')
})

test('describes pods and containers with plurals', () => {
  expect(describeUnits({ pods: 1, containers: 4 })).toBe('1 pod · 4 containers')
  expect(describeUnits({ pods: 0, containers: 1 })).toBe('1 container')
})
