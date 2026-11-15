import type { ApplicationData, SecretData } from '@/types'
import { attentionItems } from '@/utils/attention'
import { expect, test } from 'vite-plus/test'

const application = (overrides: Partial<ApplicationData>): ApplicationData => ({
  name: 'app',
  unit: 'app.service',
  kind: 'container',
  status: 'running',
  result: null,
  since: null,
  image: null,
  pods: 0,
  containers: 1,
  ports: [],
  memory: null,
  auto_update: null,
  description: null,
  url: null,
  error: null,
  ...overrides,
})

const secret = (overrides: Partial<SecretData>): SecretData => ({
  name: 'secret',
  driver: 'file',
  updated_at: null,
  used_by: ['app'],
  missing: false,
  ...overrides,
})

test('reports a failed application with the secret it is missing', () => {
  const items = attentionItems(
    [application({ name: 'home-assistant', status: 'failed', error: 'Secret ha-mqtt-password does not exist' })],
    [secret({ name: 'ha-mqtt-password', missing: true, used_by: ['home-assistant'] })],
  )

  expect(items).toEqual([
    {
      kind: 'failed',
      application: 'home-assistant',
      detail: 'Secret ha-mqtt-password does not exist',
      missingSecret: 'ha-mqtt-password',
    },
  ])
})

test('reports missing secrets that no failure explains, and unused secrets', () => {
  const items = attentionItems(
    [application({ name: 'forgejo' })],
    [
      secret({ name: 'forgejo-smtp', missing: true, used_by: ['forgejo'] }),
      secret({ name: 'backup-s3-key', used_by: [] }),
      secret({ name: 'immich-db-password' }),
    ],
  )

  expect(items).toEqual([
    { kind: 'missing-secret', secret: 'forgejo-smtp', usedBy: ['forgejo'] },
    { kind: 'unused-secret', secret: 'backup-s3-key' },
  ])
})
