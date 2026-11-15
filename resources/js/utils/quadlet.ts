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

export function isQuadletFile(name: string): boolean {
  return quadletExtensions.includes(name.split('.').pop() ?? '')
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
