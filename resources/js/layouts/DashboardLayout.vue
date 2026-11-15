<script setup lang="ts">
import EnvironmentSwitcher from '@/components/Environments/EnvironmentSwitcher.vue'
import SettingsModal from '@/components/Settings/SettingsModal.vue'
import AppLogo from '@/components/Ui/AppLogo.vue'
import MobileTabBar from '@/components/Ui/MobileTabBar.vue'
import { useAppearance } from '@/composables/appearance'
import { useAuth } from '@/composables/auth'
import { useFlash } from '@/composables/flash'
import { useNavigation } from '@/composables/navigation'
import { provideSettings } from '@/composables/settings'
import type { NavigationMenuItem } from '@nuxt/ui'
import { ref } from 'vue'

const { nonce } = useAppearance()
useFlash()

const { environment, links, sidebarItems, searchGroups } = useNavigation()

const isSidebarOpen = ref(false)

const { logOut } = useAuth()

const { isOpen: isSettingsOpen, section: settingsSection, open: openSettings } = provideSettings()

const footerItems: NavigationMenuItem[] = [
  { label: 'Journal', icon: 'i-lucide-scroll-text', disabled: true },
  { label: 'Settings', icon: 'i-lucide-settings', onSelect: () => openSettings('account') },
  { label: 'Log out', icon: 'i-lucide-log-out', class: 'lg:hidden', onSelect: logOut },
]
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
          v-if="links.length"
          :links="links"
          class="lg:hidden"
          @more="isSidebarOpen = true"
        />
      </div>
    </UDashboardGroup>

    <SettingsModal
      v-model:open="isSettingsOpen"
      v-model:section="settingsSection"
    />
  </UApp>
</template>
