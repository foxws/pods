export type QuadletEntry = [key: string, value: string | number | boolean | null | undefined]

export type QuadletSections = Record<string, QuadletEntry[]>

export type QuadletFile = { name: string; content: string }

/**
 * Writes the sections as a Quadlet unit file, skipping empty values and empty sections.
 */
export function renderQuadlet(sections: QuadletSections, header?: string): string {
  const blocks = Object.entries(sections)
    .map(([section, entries]) => {
      const lines = entries
        .filter(([, value]) => value !== null && value !== undefined && value !== '' && value !== false)
        .map(([key, value]) => `${key}=${value === true ? 'true' : value}`)

      return lines.length ? `[${section}]\n${lines.join('\n')}` : null
    })
    .filter(Boolean)

  return `${header ? `# ${header}\n` : ''}${blocks.join('\n\n')}\n`
}

/**
 * The secret names a Quadlet file references with Secret=.
 */
export function secretsIn(content: string): string[] {
  return [...content.matchAll(/^Secret=([^,\s]+)/gm)].map((match) => match[1] as string)
}

/**
 * The first value of a key, e.g. the Image= of a container.
 */
export function valueOf(content: string, key: string): string | null {
  return new RegExp(`^${key}=(.+)$`, 'm').exec(content)?.[1]?.trim() ?? null
}

export const quadletExtensions = [
  'container',
  'pod',
  'volume',
  'network',
  'kube',
  'image',
  'build',
  'env',
  'yaml',
  'yml',
]

/**
 * The repository manifest Pods reads when fetching from Git. It's never installed.
 */
export const manifestFile = 'pods.yml'

/**
 * Files that units use, rather than units themselves: Kubernetes YAML for a .kube unit and
 * environment files. A repository root has plenty of unrelated ones, such as pnpm-lock.yaml.
 */
const supportExtensions = ['env', 'yaml', 'yml']

const extensionOf = (name: string): string => name.split('.').pop() ?? ''

export function isQuadletFile(name: string): boolean {
  return name !== manifestFile && quadletExtensions.includes(extensionOf(name))
}

/**
 * The files to tick when they're fetched or uploaded: every unit, and a YAML or .env file only
 * when one of those units refers to it by name.
 */
export function selectedByDefault(files: QuadletFile[]): string[] {
  const units = files.filter((file) => isQuadletFile(file.name) && !supportExtensions.includes(extensionOf(file.name)))

  return files
    .filter((file) => isQuadletFile(file.name))
    .filter(
      (file) =>
        !supportExtensions.includes(extensionOf(file.name)) || units.some((unit) => unit.content.includes(file.name)),
    )
    .map((file) => file.name)
}

/**
 * What a Quadlet file defines, for the file list of the install page.
 */
export function describeQuadlet(file: QuadletFile): string {
  const kind = file.name.split('.').pop()

  if (kind === 'container') {
    return valueOf(file.content, 'Image') ?? 'container'
  }

  if (kind === 'pod') {
    const ports = [...file.content.matchAll(/^PublishPort=(.+)$/gm)].map((match) => match[1])

    return ports.length ? `pod · ports ${ports.join(', ')}` : 'pod'
  }

  return kind ?? 'file'
}

/**
 * The unit types a section of a .quadlets file can be, by its section header. Podman also has
 * [Artifact], which Pods doesn't install.
 */
const multiQuadletKinds = ['container', 'pod', 'volume', 'network', 'kube', 'image', 'build']

const multiQuadletName = /^[A-Za-z0-9][A-Za-z0-9_.@-]{0,127}$/

export type SplitQuadlets = { files: QuadletFile[]; errors: string[] }

/**
 * Splits each .quadlets file (Podman 5.8+) into its units, following Podman's parseMultiQuadletFile:
 * sections are separated by a line holding only ---, each needs a "# FileName=NAME" comment, and the
 * first recognised [Section] header decides the extension. Other files pass through unchanged.
 */
export function splitQuadlets(files: QuadletFile[]): SplitQuadlets {
  const result: SplitQuadlets = { files: [], errors: [] }

  const add = (file: QuadletFile, origin: string): void => {
    if (result.files.some((existing) => existing.name === file.name)) {
      result.errors.push(`${origin}: ${file.name} is already in the list.`)

      return
    }

    result.files.push(file)
  }

  for (const file of files) {
    if (extensionOf(file.name) !== 'quadlets') {
      add(file, file.name)

      continue
    }

    const sections = file.content
      .split('\n')
      .reduce<string[][]>(
        (sections, line) => {
          if (line.trim() === '---') {
            sections.push([])
          } else {
            sections[sections.length - 1]?.push(line)
          }

          return sections
        },
        [[]],
      )
      .map((lines) => lines.join('\n').trim())

    if (sections.every((content) => content === '')) {
      result.errors.push(`${file.name}: has no Quadlet sections.`)

      continue
    }

    sections.forEach((content, index) => {
      if (content === '') {
        return
      }

      const origin = `${file.name}, section ${index + 1}`
      const lines = content.split('\n').map((line) => line.trim())

      const kind = lines
        .filter((line) => line.startsWith('[') && line.endsWith(']'))
        .map((line) => line.slice(1, -1).toLowerCase())
        .find((section) => [...multiQuadletKinds, 'artifact'].includes(section))

      const name = lines
        .filter((line) => line.startsWith('#'))
        .map((line) => line.slice(1).trim())
        .find((comment) => comment.startsWith('FileName='))
        ?.slice('FileName='.length)
        .trim()

      if (!kind) {
        result.errors.push(`${origin}: no [Container], [Pod], [Volume], [Network], [Kube], [Image] or [Build] section.`)
      } else if (kind === 'artifact') {
        result.errors.push(`${origin}: Pods doesn't install .artifact units yet.`)
      } else if (!name) {
        result.errors.push(`${origin}: missing a "# FileName=NAME" comment.`)
      } else if (!multiQuadletName.test(name)) {
        result.errors.push(`${origin}: "${name}" isn't a valid file name.`)
      } else {
        add({ name: `${name}.${kind}`, content: `${content}\n` }, origin)
      }
    })
  }

  return result
}
