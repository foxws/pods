import {
  describeQuadlet,
  dropInQuadlet,
  dropInTemplate,
  isLocalDropIn,
  isLpodDropIn,
  isQuadletFile,
  localDropInName,
  renderQuadlet,
  secretsIn,
  selectedByDefault,
  splitQuadlets,
  takesDropIns,
} from '@/utils/quadlet'
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

test('splits a .quadlets file into its units', () => {
  const content = [
    '---',
    '# FileName=web',
    '[Unit]',
    'Description=Web',
    '',
    '[Container]',
    'Image=nginx:1',
    'Volume=web-data.volume:/data',
    '  ---  ',
    '#FileName=web-data',
    '[Volume]',
    '---',
    '',
  ].join('\n')

  expect(
    splitQuadlets([
      { name: 'web.quadlets', content },
      { name: 'web.env', content: 'A=1\n' },
    ]),
  ).toEqual({
    files: [
      {
        name: 'web.container',
        content:
          '# FileName=web\n[Unit]\nDescription=Web\n\n[Container]\nImage=nginx:1\nVolume=web-data.volume:/data\n',
      },
      { name: 'web-data.volume', content: '#FileName=web-data\n[Volume]\n' },
      { name: 'web.env', content: 'A=1\n' },
    ],
    errors: [],
  })
})

test('skips the sections of a .quadlets file that podman would refuse', () => {
  const content = [
    '[Container]\nImage=nginx:1',
    '# FileName=data\n[Service]\nType=oneshot',
    '# FileName=../data\n[Volume]',
    '# FileName=cache\n[Artifact]\nArtifact=quay.io/a/b:1',
    '# FileName=web\n[Network]',
    '# FileName=ok\n[Network]',
  ].join('\n---\n')

  expect(
    splitQuadlets([
      { name: 'web.network', content: '[Network]\n' },
      { name: 'app.quadlets', content },
    ]),
  ).toEqual({
    files: [
      { name: 'web.network', content: '[Network]\n' },
      { name: 'ok.network', content: '# FileName=ok\n[Network]\n' },
    ],
    errors: [
      'app.quadlets, section 1: missing a "# FileName=NAME" comment.',
      'app.quadlets, section 2: no [Container], [Pod], [Volume], [Network], [Kube], [Image] or [Build] section.',
      'app.quadlets, section 3: "../data" isn\'t a valid file name.',
      "app.quadlets, section 4: Pods doesn't install .artifact units yet.",
      'app.quadlets, section 5: web.network is already in the list.',
    ],
  })
  expect(splitQuadlets([{ name: 'empty.quadlets', content: '---\n\n---\n' }]).errors).toEqual([
    'empty.quadlets: has no Quadlet sections.',
  ])
})

test("tells drop-ins from Quadlets, and lpod's own and local ones from the rest", () => {
  expect(dropInQuadlet('hello.container.d/memory.conf')).toBe('hello.container')
  expect(dropInQuadlet('hello.container')).toBeNull()
  expect(isLpodDropIn('hello.container.d/lpod-ondemand.conf')).toBe(true)
  expect(isLpodDropIn('hello.container.d/memory.conf')).toBe(false)
  expect(isLocalDropIn('hello.container.d/memory.local.conf')).toBe(true)
  expect(isLocalDropIn('hello.container.d/memory.conf')).toBe(false)
  expect(takesDropIns('hello.pod')).toBe(true)
  expect(takesDropIns('hello.env')).toBe(false)
})

test('names a new drop-in as a local one, and starts it with the section of its Quadlet', () => {
  expect(localDropInName('hello.container', 'memory')).toBe('hello.container.d/memory.local.conf')
  expect(localDropInName('hello.container', 'memory.local.conf')).toBe('hello.container.d/memory.local.conf')
  expect(dropInTemplate('hello.pod')).toBe('[Pod]\n')
})
