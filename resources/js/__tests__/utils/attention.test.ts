import type { ApplicationData, ImageData, SecretData } from '@/types'
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

test('reports the applications that have image updates once, before unused secrets', () => {
  const items = attentionItems(
    [application({ name: 'immich' }), application({ name: 'jellyfin' })],
    [secret({ name: 'backup-s3-key', used_by: [] })],
    [
      image({ used_by: ['jellyfin'], update: '10.11' }),
      image({ used_by: ['immich', 'paperless'], update: '8-alpine' }),
      image({ used_by: ['immich'], update: null }),
    ],
  )

  expect(items).toEqual([
    { kind: 'image-updates', applications: ['immich', 'jellyfin', 'paperless'] },
    { kind: 'unused-secret', secret: 'backup-s3-key' },
  ])
})
