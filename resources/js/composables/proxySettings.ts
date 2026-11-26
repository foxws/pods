import type { ProxyMode, ProxySettingsFormValues } from '@/types'
import { useForm } from '@inertiajs/vue3'

/**
 * Proxy settings as a host or the Pods defaults hold them, where anything may be missing.
 */
export type ProxySettingsSource = {
  mode: string | null
  email: string | null
  domain: string | null
  dns_provider: string | null
  resolvers: string | null
  http_port: number | null
  https_port: number | null
  image: string | null
}

/**
 * The fields sent for a proxy: blank text becomes null, so the host or Pods falls back to its default.
 */
export type ProxySettingsPayload = Omit<
  ProxySettingsFormValues,
  'email' | 'domain' | 'resolvers' | 'image' | 'dns_token'
> & {
  email: string | null
  domain: string | null
  resolvers: string | null
  image: string | null
  dns_token: string | null
}

/**
 * One form for the proxy of a host and for the Pods-wide defaults, which share their fields.
 */
export function useProxySettingsForm() {
  const form = useForm<ProxySettingsFormValues>({
    mode: 'letsencrypt',
    email: '',
    domain: '',
    dns_provider: 'cloudflare',
    dns_token: '',
    resolvers: '',
    http_port: 80,
    https_port: 443,
    image: '',
  })

  /**
   * Make the source what the form starts from and resets to. The DNS token is never read back.
   */
  const fill = (source: ProxySettingsSource): void => {
    form.defaults({
      mode: (source.mode as ProxyMode | null) ?? 'letsencrypt',
      email: source.email ?? '',
      domain: source.domain ?? '',
      dns_provider: source.dns_provider ?? 'cloudflare',
      dns_token: '',
      resolvers: source.resolvers ?? '',
      http_port: source.http_port ?? 80,
      https_port: source.https_port ?? 443,
      image: source.image ?? '',
    })
    form.reset()
  }

  return { form, fill }
}

export function toProxySettingsPayload(values: ProxySettingsFormValues): ProxySettingsPayload {
  return {
    ...values,
    email: values.email.trim() || null,
    domain: values.domain.trim() || null,
    resolvers: values.resolvers.trim() || null,
    image: values.image.trim() || null,
    dns_token: values.dns_token.trim() || null,
  }
}
