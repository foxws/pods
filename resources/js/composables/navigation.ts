import { index as applications } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import overview from '@/actions/Modules/Web/Environments/Controllers/EnvironmentOverviewController'
import { index as secrets } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import { usePage } from '@inertiajs/vue3'
import type { CommandPaletteGroup, NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'

export type NavigationLink = {
  label: string
  shortLabel: string
  icon: string
  to: string
  active: boolean
}

const planned: { label: string; icon: string }[] = [
  { label: 'Containers', icon: 'i-lucide-box' },
  { label: 'Pods', icon: 'i-lucide-boxes' },
  { label: 'Images', icon: 'i-lucide-disc-3' },
  { label: 'Volumes', icon: 'i-lucide-hard-drive' },
  { label: 'Networks', icon: 'i-lucide-network' },
]

/**
 * One navigation model for the sidebar, the mobile tab bar and the search palette.
 */
export function useNavigation() {
  const page = usePage()

  const environment = computed(() => page.props.environment ?? null)

  const isCurrent = (url: string): boolean =>
    page.url === url || page.url.startsWith(`${url}/`) || page.url.startsWith(`${url}?`)

  const links = computed<NavigationLink[]>(() => {
    if (!environment.value) {
      return []
    }

    const overviewUrl = overview.url(environment.value.id)
    const applicationsUrl = applications.url(environment.value.id)
    const secretsUrl = secrets.url(environment.value.id)

    return [
      {
        label: 'Overview',
        shortLabel: 'Overview',
        icon: 'i-lucide-house',
        to: overviewUrl,
        active: page.url === overviewUrl || page.url.startsWith(`${overviewUrl}?`),
      },
      {
        label: 'Applications',
        shortLabel: 'Apps',
        icon: 'i-lucide-layers',
        to: applicationsUrl,
        active: isCurrent(applicationsUrl),
      },
      {
        label: 'Secrets',
        shortLabel: 'Secrets',
        icon: 'i-lucide-key-round',
        to: secretsUrl,
        active: isCurrent(secretsUrl),
      },
    ]
  })

  const sidebarItems = computed<NavigationMenuItem[][]>(() => {
    if (!links.value.length) {
      return []
    }

    const [overviewLink, applicationsLink, secretsLink] = links.value.map<NavigationMenuItem>(
      ({ label, icon, to, active }) => ({
        label,
        icon,
        to,
        active,
      }),
    )
    const soon = planned.map<NavigationMenuItem>(({ label, icon }) => ({ label, icon, disabled: true }))

    return [[overviewLink, applicationsLink, ...soon, secretsLink]]
  })

  const searchGroups = computed<CommandPaletteGroup[]>(() => [
    {
      id: 'navigation',
      label: 'Go to',
      items: links.value.map(({ label, icon, to }) => ({ label, icon, to })),
    },
    {
      id: 'environments',
      label: 'Environments',
      items: (page.props.environments ?? []).map((item) => ({
        label: item.name,
        icon: item.is_local ? 'i-lucide-house' : 'i-lucide-server',
        to: overview.url(item.id),
      })),
    },
  ])

  return { environment, links, sidebarItems, searchGroups }
}
