<script setup lang="ts">
import SettingsModal from '@/components/Settings/SettingsModal.vue'
import AppHeader from '@/components/Ui/AppHeader.vue'
import { useAppearance } from '@/composables/appearance'
import { useFlash } from '@/composables/flash'
import { provideSettings } from '@/composables/settings'

const { nonce } = useAppearance()
useFlash()

const { isOpen: isSettingsOpen, section: settingsSection } = provideSettings()
</script>

<template>
  <UApp :nonce="nonce">
    <div class="flex min-h-dvh flex-col">
      <AppHeader />

      <UMain class="flex-1">
        <UContainer class="py-8">
          <slot />
        </UContainer>
      </UMain>
    </div>

    <SettingsModal
      v-model:open="isSettingsOpen"
      v-model:section="settingsSection"
    />
  </UApp>
</template>
