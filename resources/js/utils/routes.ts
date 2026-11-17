/**
 * The hostname Pods suggests for a route, as SuggestRoutes does on the server: the application's name
 * for the main route, and the role with it for the others. Under home.arpa that's stry.home.arpa and
 * ws.stry.home.arpa. Behind a wildcard site, whose certificate covers one label, it's stry.DOMAIN and
 * stry-ws.DOMAIN.
 */
export function suggestedHost(
  application: string,
  role: string,
  main: boolean,
  wildcard: string | null = null,
): string {
  const name = application.replace(/[^A-Za-z0-9-]+/g, '-').toLowerCase() || 'app'

  if (wildcard) {
    return main ? `${name}.${wildcard}` : `${name}-${role}.${wildcard}`
  }

  return main ? `${name}.home.arpa` : `${role}.${name}.home.arpa`
}
