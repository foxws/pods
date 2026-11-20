import { index as applications } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { index as containers } from '@/actions/Modules/Web/Containers/Controllers/ContainerController'
import overview from '@/actions/Modules/Web/Environments/Controllers/EnvironmentOverviewController'
import { index as images } from '@/actions/Modules/Web/Images/Controllers/ImageController'
import journal from '@/actions/Modules/Web/Journal/Controllers/JournalController'
import {
  index as notifications,
  environment as notificationsInEnvironment,
} from '@/actions/Modules/Web/Notifications/Controllers/NotificationController'
import { index as networks } from '@/actions/Modules/Web/Networks/Controllers/NetworkController'
import { index as pods } from '@/actions/Modules/Web/Pods/Controllers/PodController'
import { index as secrets } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import { show as settingsShow, showInEnvironment } from '@/actions/Modules/Web/Settings/Controllers/SettingsController'
import { index as volumes } from '@/actions/Modules/Web/Volumes/Controllers/VolumeController'
import { usePage } from '@inertiajs/vue3'
import type { CommandPaletteGroup, NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'

export type SettingsSection = 'account' | 'security' | 'general' | 'proxy'

export type NavigationLink = {
  label: string
  shortLabel: string
  icon: string
  to: string
  active: boolean
  /** Shown in the bottom tab bar on phones; the rest sit behind More. */
  inTabBar: boolean
}

type Section = Omit<NavigationLink, 'to' | 'active'> & {
  url: (environment: string) => string
  exact?: boolean
}

const sections: Section[] = [
  {
    label: 'Overview',
    shortLabel: 'Overview',
    icon: 'i-lucide-house',
    url: (id) => overview.url(id),
    exact: true,
    inTabBar: true,
  },
  {
    label: 'Applications',
    shortLabel: 'Apps',
    icon: 'i-lucide-layers',
    url: (id) => applications.url(id),
    inTabBar: true,
  },
  {
    label: 'Containers',
    shortLabel: 'Containers',
    icon: 'i-lucide-box',
    url: (id) => containers.url(id),
    inTabBar: false,
  },
  { label: 'Pods', shortLabel: 'Pods', icon: 'i-lucide-boxes', url: (id) => pods.url(id), inTabBar: false },
  { label: 'Images', shortLabel: 'Images', icon: 'i-lucide-disc-3', url: (id) => images.url(id), inTabBar: false },
  {
    label: 'Volumes',
    shortLabel: 'Volumes',
    icon: 'i-lucide-hard-drive',
    url: (id) => volumes.url(id),
    inTabBar: false,
  },
  {
    label: 'Networks',
    shortLabel: 'Networks',
    icon: 'i-lucide-network',
    url: (id) => networks.url(id),
    inTabBar: false,
  },
  { label: 'Secrets', shortLabel: 'Secrets', icon: 'i-lucide-key-round', url: (id) => secrets.url(id), inTabBar: true },
]

/**
 * One navigation model for the sidebar, the mobile tab bar and the search palette.
 */
export function useNavigation() {
  const page = usePage()

  const environment = computed(() => page.props.environment ?? null)

  const isCurrent = (url: string, exact = false): boolean =>
    page.url === url || page.url.startsWith(`${url}?`) || (!exact && page.url.startsWith(`${url}/`))

  const links = computed<NavigationLink[]>(() => {
    if (!environment.value) {
      return []
    }

    const id = environment.value.id

    return sections.map(({ url, exact, ...section }) => ({
      ...section,
      to: url(id),
      active: isCurrent(url(id), exact),
    }))
  })

  const tabBarLinks = computed(() => links.value.filter((link) => link.inTabBar))

  const journalLink = computed<NavigationMenuItem>(() =>
    environment.value
      ? {
          label: 'Journal',
          icon: 'i-lucide-scroll-text',
          to: journal.url(environment.value.id),
          active: isCurrent(journal.url(environment.value.id)),
        }
      : { label: 'Journal', icon: 'i-lucide-scroll-text', disabled: true },
  )

  /**
   * The user's notifications, inside the selected environment like the settings, so the sidebar keeps its host.
   */
  const notificationsLink = computed(() => {
    const to = environment.value ? notificationsInEnvironment.url(environment.value.id) : notifications.url()

    return { to, active: isCurrent(to) }
  })

  /**
   * Settings stay inside the selected environment, so the sidebar keeps its host; without one they're under /settings.
   */
  const settingsUrl = (section: SettingsSection = 'account'): string =>
    environment.value ? showInEnvironment.url([environment.value.id, section]) : settingsShow.url(section)

  const sidebarItems = computed<NavigationMenuItem[][]>(() => [
    links.value.map<NavigationMenuItem>(({ label, icon, to, active }) => ({ label, icon, to, active })),
  ])

  const searchGroups = computed<CommandPaletteGroup[]>(() => [
    {
      id: 'navigation',
      label: 'Go to',
      items: [
        ...links.value.map(({ label, icon, to }) => ({ label, icon, to })),
        { label: 'Notifications', icon: 'i-lucide-bell', to: notificationsLink.value.to },
        ...(environment.value
          ? [{ label: 'Journal', icon: 'i-lucide-scroll-text', to: journal.url(environment.value.id) }]
          : []),
      ],
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

  return { environment, links, tabBarLinks, journalLink, notificationsLink, settingsUrl, sidebarItems, searchGroups }
}
