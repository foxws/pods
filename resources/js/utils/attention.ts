import type { ApplicationData, SecretData } from '@/types'

export type AttentionItem =
  | { kind: 'failed'; application: string; detail: string; missingSecret: string | null }
  | { kind: 'missing-secret'; secret: string; usedBy: string[] }
  | { kind: 'unused-secret'; secret: string }

export function missingSecretIn(error: string | null): string | null {
  return error?.match(/Secret (\S+) does not exist/)?.[1] ?? null
}

/**
 * What an owner should look at first: failed applications, then secrets that are missing or unused.
 */
export function attentionItems(applications: ApplicationData[], secrets: SecretData[]): AttentionItem[] {
  const failed = applications.filter((application) => application.status === 'failed')
  const explained = new Set(failed.map((application) => missingSecretIn(application.error)).filter(Boolean))

  return [
    ...failed.map<AttentionItem>((application) => ({
      kind: 'failed',
      application: application.name,
      detail: application.error ?? `The unit exited with ${application.result ?? 'an error'}.`,
      missingSecret: missingSecretIn(application.error),
    })),
    ...secrets
      .filter((secret) => secret.missing && !explained.has(secret.name))
      .map<AttentionItem>((secret) => ({ kind: 'missing-secret', secret: secret.name, usedBy: secret.used_by })),
    ...secrets
      .filter((secret) => !secret.missing && secret.used_by.length === 0)
      .map<AttentionItem>((secret) => ({ kind: 'unused-secret', secret: secret.name })),
  ]
}
