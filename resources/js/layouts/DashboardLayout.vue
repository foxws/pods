<script setup lang="ts">
import EnvironmentSwitcher from '@/components/Environments/EnvironmentSwitcher.vue'
import AppLogo from '@/components/Ui/AppLogo.vue'
import MobileTabBar from '@/components/Ui/MobileTabBar.vue'
import { useAppearance } from '@/composables/appearance'
import { useAuth } from '@/composables/auth'
import { useFlash } from '@/composables/flash'
import { useNavigation } from '@/composables/navigation'
import { index as settingsIndex } from '@/actions/Modules/Web/Settings/Controllers/SettingsController'
import type { NavigationMenuItem } from '@nuxt/ui'
import { usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const { nonce } = useAppearance()
useFlash()

const { environment, tabBarLinks, journalLink, sidebarItems, searchGroups } = useNavigation()

const isSidebarOpen = ref(false)

const { logOut } = useAuth()

const page = usePage()

/**
 * Settings covers the account pages, the hosts list and the settings of each host.
 */
const isSettingsPage = computed(() =>
  /^\/(settings(\/|$|\?)|environments\/?($|\?)|environments\/[^/]+\/settings)/.test(page.url),
)

const footerItems = computed<NavigationMenuItem[]>(() => [
  journalLink.value,
  { label: 'Settings', icon: 'i-lucide-settings', to: settingsIndex.url(), active: isSettingsPage.value },
  { label: 'Log out', icon: 'i-lucide-log-out', class: 'lg:hidden', onSelect: logOut },
])
</script>

<template>
  <UApp :nonce="nonce">
    <UDashboardGroup unit="rem">
      <UDashboardSidebar
        v-model:open="isSidebarOpen"
        collapsible
        resizable
        :ui="{ body: 'gap-5', footer: 'hidden' }"
      >
        <template #header="{ collapsed }">
          <AppLogo
            :compact="collapsed"
            location="/"
          />
        </template>

        <template #default="{ collapsed }">
          <EnvironmentSwitcher
            :current="environment"
            :collapsed="collapsed"
          />

          <UNavigationMenu
            :collapsed="collapsed"
            :items="sidebarItems"
            orientation="vertical"
            tooltip
          />

          <UNavigationMenu
            :collapsed="collapsed"
            :items="footerItems"
            orientation="vertical"
            tooltip
            class="border-t border-default pt-3"
          />
        </template>
      </UDashboardSidebar>

      <UDashboardSearch
        :groups="searchGroups"
        shortcut="/"
      />

      <div class="flex min-h-0 min-w-0 flex-1 flex-col">
        <slot />

        <MobileTabBar
          v-if="tabBarLinks.length"
          :links="tabBarLinks"
          class="lg:hidden"
          @more="isSidebarOpen = true"
        />
      </div>
    </UDashboardGroup>
  </UApp>
</template>
