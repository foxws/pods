<script setup lang="ts">
import { index as applications } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { index as secrets } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import EnvironmentSwitcher from '@/components/Environments/EnvironmentSwitcher.vue'
import AppLogo from '@/components/Ui/AppLogo.vue'
import UserMenu from '@/components/Ui/UserMenu.vue'
import { useAppearance } from '@/composables/appearance'
import { useFlash } from '@/composables/flash'
import { usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'

const { nonce } = useAppearance()
useFlash()

const page = usePage()

const environment = computed(() => page.props.environment ?? null)

const isCurrent = (url: string): boolean =>
  page.url === url || page.url.startsWith(`${url}/`) || page.url.startsWith(`${url}?`)

const later = (label: string, icon: string): NavigationMenuItem => ({
  label,
  icon,
  disabled: true,
  badge: { label: 'Soon', color: 'neutral', variant: 'outline', size: 'sm' },
})

const items = computed<NavigationMenuItem[][]>(() => {
  if (!environment.value) {
    return []
  }

  const applicationsUrl = applications.url(environment.value.id)
  const secretsUrl = secrets.url(environment.value.id)

  return [
    [
      { label: 'Applications', icon: 'i-lucide-layers', to: applicationsUrl, active: isCurrent(applicationsUrl) },
      later('Containers', 'i-lucide-box'),
      later('Pods', 'i-lucide-boxes'),
      later('Images', 'i-lucide-disc-3'),
      later('Volumes', 'i-lucide-hard-drive'),
      later('Networks', 'i-lucide-network'),
      { label: 'Secrets', icon: 'i-lucide-key-round', to: secretsUrl, active: isCurrent(secretsUrl) },
      later('Journal', 'i-lucide-scroll-text'),
    ],
  ]
})
</script>

<template>
  <UApp :nonce="nonce">
    <UDashboardGroup unit="rem">
      <UDashboardSidebar
        collapsible
        resizable
        :ui="{ footer: 'border-t border-default' }"
      >
        <template #header="{ collapsed }">
          <AppLogo v-if="!collapsed" />
        </template>

        <template #default="{ collapsed }">
          <EnvironmentSwitcher
            :current="environment"
            :collapsed="collapsed"
          />

          <UNavigationMenu
            :collapsed="collapsed"
            :items="items"
            orientation="vertical"
            tooltip
          />
        </template>

        <template #footer>
          <UserMenu />
        </template>
      </UDashboardSidebar>

      <slot />
    </UDashboardGroup>
  </UApp>
</template>
