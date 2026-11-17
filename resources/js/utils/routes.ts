/**
 * The hostname Pods suggests for a route, as SuggestRoutes does on the server: the application's name
 * under home.arpa for the main route (stry.home.arpa), with the role in front for the others
 * (websockets.stry.home.arpa).
 */
export function suggestedHost(application: string, role: string, main: boolean): string {
  const name = application.replace(/[^A-Za-z0-9-]+/g, '-').toLowerCase() || 'app'

  return main ? `${name}.home.arpa` : `${role}.${name}.home.arpa`
}
