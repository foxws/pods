import type { ReachableUpstreamData } from '@/types'

/**
 * The hostname Pods suggests for a route, as SuggestRoutes does on the server: the application's name
 * for the main route, and the role with it for the others. Under home.arpa, or the proxy defaults'
 * domain when they have one, that's hello.home.arpa and ws.hello.home.arpa. Behind a wildcard site, whose
 * certificate covers one label, it's hello.DOMAIN and hello-ws.DOMAIN.
 */
export function suggestedHost(
  application: string,
  role: string,
  main: boolean,
  wildcard: string | null = null,
  domain: string | null = null,
): string {
  const name = application.replace(/[^A-Za-z0-9-]+/g, '-').toLowerCase() || 'app'

  if (wildcard) {
    return main ? `${name}.${wildcard}` : `${name}-${role}.${wildcard}`
  }

  const base = domain || 'home.arpa'

  return main ? `${name}.${base}` : `${role}.${name}.${base}`
}

/**
 * A reachable container as a route's upstream, such as systemd-hello:8000. Without a known port,
 * it ends in a colon, so the port can be typed after it.
 */
export function reachableUpstream(reachable: ReachableUpstreamData): string {
  return `${reachable.name}:${reachable.port ?? ''}`
}
