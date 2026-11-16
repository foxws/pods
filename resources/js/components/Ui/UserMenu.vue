<script setup lang="ts">
import { useAuth } from '@/composables/auth'
import { show as settingsShow } from '@/actions/Modules/Web/Settings/Controllers/SettingsController'
import type { DropdownMenuItem } from '@nuxt/ui'
import { computed } from 'vue'

const { user, hasRole, logOut } = useAuth()

const items = computed<DropdownMenuItem[][]>(() => [
  [
    {
      label: user.value?.name ?? user.value?.email,
      type: 'label',
    },
  ],
  [
    {
      label: 'Settings',
      icon: 'i-lucide-settings',
      to: settingsShow.url('account'),
    },
    ...(hasRole('super-admin')
      ? [
          {
            label: 'General',
            icon: 'i-lucide-sliders-horizontal',
            to: settingsShow.url('general'),
          },
        ]
      : []),
  ],
  [
    {
      label: 'Log out',
      icon: 'i-lucide-log-out',
      onClick: logOut,
    },
  ],
])
</script>

<template>
  <UDropdownMenu
    :items="items"
    :content="{ align: 'end', collisionPadding: 12 }"
  >
    <UButton
      :avatar="{ alt: user?.name ?? 'User', size: 'sm' }"
      color="neutral"
      variant="ghost"
      square
      aria-label="Account"
      class="rounded-full p-0.5"
    />
  </UDropdownMenu>
</template>
