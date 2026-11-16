import { describeQuadlet, isQuadletFile, renderQuadlet, secretsIn, selectedByDefault } from '@/utils/quadlet'
import { expect, test } from 'vite-plus/test'

test('writes sections and skips empty values and sections', () => {
  expect(
    renderQuadlet(
      {
        Container: [
          ['Image', 'docker.io/louislam/uptime-kuma:2'],
          ['Pod', ''],
          ['ReadOnly', true],
          ['AutoUpdate', null],
          ['PublishPort', '3001:3001/tcp'],
        ],
        Service: [['Restart', false]],
        Install: [['WantedBy', 'default.target']],
      },
      'uptime-kuma.container',
    ),
  ).toBe(
    '# uptime-kuma.container\n[Container]\nImage=docker.io/louislam/uptime-kuma:2\nReadOnly=true\nPublishPort=3001:3001/tcp\n\n[Install]\nWantedBy=default.target\n',
  )
})

test('gives the names of the referenced secrets', () => {
  expect(secretsIn('[Container]\nSecret=db-password,type=env,target=DB\nSecret=smtp\n# Secret=commented\n')).toEqual([
    'db-password',
    'smtp',
  ])
})

test('only accepts quadlet extensions', () => {
  expect(isQuadletFile('nextcloud.pod')).toBe(true)
  expect(isQuadletFile('README.md')).toBe(false)
  expect(isQuadletFile('pods.yml')).toBe(false)
})

test('only ticks the yaml and env files that a unit refers to', () => {
  expect(
    selectedByDefault([
      { name: 'app.kube', content: '[Kube]\nYaml=app.yaml\n' },
      { name: 'app.yaml', content: 'kind: Pod\n' },
      { name: 'web.container', content: '[Container]\nEnvironmentFile=web.env\n' },
      { name: 'web.env', content: 'A=1\n' },
      { name: 'pnpm-lock.yaml', content: 'lockfileVersion: 9\n' },
      { name: 'other.env', content: 'B=2\n' },
      { name: 'pods.yml', content: 'version: 1\n' },
      { name: 'README.md', content: '# App\n' },
    ]),
  ).toEqual(['app.kube', 'app.yaml', 'web.container', 'web.env'])
})

test('names the image of a container and the ports of a pod', () => {
  expect(describeQuadlet({ name: 'app.container', content: 'Image=nextcloud:31\n' })).toBe('nextcloud:31')
  expect(describeQuadlet({ name: 'app.pod', content: 'PublishPort=8443:443\n' })).toBe('pod · ports 8443:443')
})
