<script setup lang="ts">
import EnvironmentSwitcher from '@/components/Environments/EnvironmentSwitcher.vue'
import AppLogo from '@/components/Ui/AppLogo.vue'
import MobileTabBar from '@/components/Ui/MobileTabBar.vue'
import UserMenu from '@/components/Ui/UserMenu.vue'
import { useAppearance } from '@/composables/appearance'
import { useFlash } from '@/composables/flash'
import { useNavigation } from '@/composables/navigation'
import { ref } from 'vue'

const { nonce } = useAppearance()
useFlash()

const { environment, links, sidebarItems, searchGroups } = useNavigation()

const isSidebarOpen = ref(false)
</script>

<template>
  <UApp :nonce="nonce">
    <UDashboardGroup unit="rem">
      <UDashboardSidebar
        v-model:open="isSidebarOpen"
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

          <UDashboardSearchButton :collapsed="collapsed" />

          <UNavigationMenu
            :collapsed="collapsed"
            :items="sidebarItems"
            orientation="vertical"
            tooltip
          />
        </template>

        <template #footer>
          <UserMenu />
        </template>
      </UDashboardSidebar>

      <UDashboardSearch :groups="searchGroups" />

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
  </UApp>
</template>
