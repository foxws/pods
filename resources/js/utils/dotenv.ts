/**
 * Reads the KEY=value lines of a .env file, so a pods.yml can fill secrets in from it.
 * Comments, blank lines and "export " prefixes are skipped. A quoted value keeps what's
 * between the quotes; an unquoted one ends at " #".
 */
export const parseDotenv = (contents: string): Record<string, string> => {
  const values: Record<string, string> = {}

  for (const line of contents.split(/\r?\n/)) {
    const match = line.match(/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=\s*(.*)$/)

    if (!match) {
      continue
    }

    const [, key, raw] = match
    const quoted = raw.match(/^(["'])(.*)\1\s*(?:#.*)?$/)

    if (quoted) {
      values[key] = quoted[1] === '"' ? quoted[2].replace(/\\n/g, '\n').replace(/\\(["\\])/g, '$1') : quoted[2]

      continue
    }

    values[key] = raw.replace(/\s+#.*$/, '').trim()
  }

  return values
}
