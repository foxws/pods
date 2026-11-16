import type { HostEventData } from '@/types'
import { describeEvent, eventTime } from '@/utils/events'
import { expect, test } from 'vite-plus/test'

const event = (overrides: Partial<HostEventData>): HostEventData => ({
  time: '2026-11-15T10:42:03+00:00',
  type: 'container',
  action: 'start',
  name: 'immich-server',
  image: 'ghcr.io/immich-app/immich-server:v2.3.1',
  application: 'immich',
  exit_code: null,
  ...overrides,
})

test('names the application and the container it happened to', () => {
  expect(describeEvent(event({}))).toMatchObject({ subject: 'immich', summary: 'started (immich-server)' })
  expect(describeEvent(event({ name: 'vaultwarden', application: 'vaultwarden', action: 'stop' }))).toMatchObject({
    subject: 'vaultwarden',
    summary: 'stopped',
  })
  expect(describeEvent(event({ name: 'quirky_lamport', application: null, action: 'remove' }))).toMatchObject({
    subject: 'quirky_lamport',
    summary: 'was removed',
  })
})

test('marks a container that died with an error', () => {
  expect(
    describeEvent(event({ action: 'died', exit_code: 125, name: 'home-assistant', application: 'home-assistant' })),
  ).toEqual({
    subject: 'home-assistant',
    summary: 'exited with code 125',
    icon: 'i-lucide-circle-x',
    color: 'text-error',
  })
  expect(describeEvent(event({ action: 'died', exit_code: 0 })).color).toBe('text-muted')
})

test('names the image of an image event', () => {
  expect(
    describeEvent(event({ type: 'image', action: 'pull', name: 'codeberg.org/forgejo/forgejo:13', application: null })),
  ).toMatchObject({ subject: 'codeberg.org/forgejo/forgejo:13', summary: 'pulled' })
})

test('shows older events as yesterday or a date', () => {
  const now = new Date(2026, 10, 15, 12, 0)

  expect(eventTime(null, now)).toBe('–')
  expect(eventTime(new Date(2026, 10, 14, 21, 3).toISOString(), now)).toBe('Yesterday')
  expect(eventTime(new Date(2026, 10, 15, 9, 15).toISOString(), now)).not.toBe('Yesterday')
  expect(eventTime(new Date(2026, 10, 10, 9, 0).toISOString(), now)).not.toBe('Yesterday')
})
