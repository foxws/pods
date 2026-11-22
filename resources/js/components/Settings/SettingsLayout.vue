<script setup lang="ts">
import { index as environmentsIndex } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentController'
import { show as environmentSettings } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentSettingsController'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import { useAuth } from '@/composables/auth'
import { useNavigation } from '@/composables/navigation'
import { usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'

defineProps<{
  title: string
  description?: string
}>()

const page = usePage()
const { hasRole } = useAuth()
const { settingsUrl } = useNavigation()

const isCurrent = (url: string): boolean => page.url === url || page.url.startsWith(`${url}?`)

const link = (label: string, icon: string, to: string): NavigationMenuItem => ({
  label,
  icon,
  to,
  active: isCurrent(to),
})

const groups = computed<NavigationMenuItem[][]>(() => [
  [
    { label: 'Your account', type: 'label' },
    link('Account', 'i-lucide-user', settingsUrl('account')),
    link('Security', 'i-lucide-shield', settingsUrl('security')),
  ],
  [
    { label: 'Pods', type: 'label' },
    ...(hasRole('super-admin')
      ? [
          link('General', 'i-lucide-sliders-horizontal', settingsUrl('general')),
          link('Proxy', 'i-lucide-globe', settingsUrl('proxy')),
          link('Catalog', 'i-lucide-layout-grid', settingsUrl('catalog')),
        ]
      : []),
    link('Hosts', 'i-lucide-server', environmentsIndex.url()),
  ],
  ...((page.props.environments ?? []).length
    ? [
        [
          { label: 'Auto-update and defaults', type: 'label' } as NavigationMenuItem,
          ...(page.props.environments ?? []).map((environment) =>
            link(
              environment.name,
              environment.is_local ? 'i-lucide-house' : 'i-lucide-server',
              environmentSettings.url(environment.id),
            ),
          ),
        ],
      ]
    : []),
])

const flatItems = computed(() => groups.value.flat().filter((item) => item.type !== 'label'))
</script>

<template>
  <UDashboardPanel id="settings">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <div class="flex flex-col gap-6 lg:flex-row lg:gap-10">
        <nav
          aria-label="Settings"
          class="lg:w-60 lg:shrink-0"
        >
          <UNavigationMenu
            :items="groups"
            orientation="vertical"
            class="hidden lg:flex"
          />

          <UNavigationMenu
            :items="flatItems"
            orientation="horizontal"
            variant="pill"
            class="-mx-4 overflow-x-auto px-4 lg:hidden"
            :ui="{ link: 'shrink-0 whitespace-nowrap' }"
          />
        </nav>

        <div class="flex min-w-0 flex-1 flex-col gap-6 lg:max-w-4xl">
          <PageHeading
            :title="title"
            :description="description"
          >
            <template
              v-if="$slots.actions"
              #actions
            >
              <slot name="actions" />
            </template>
          </PageHeading>

          <slot />
        </div>
      </div>
    </template>
  </UDashboardPanel>
</template>
