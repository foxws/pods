<script setup lang="ts">
import { index as applications } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { index as environments } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentController'
import type { EnvironmentData } from '@/types'
import { usePage } from '@inertiajs/vue3'
import type { DropdownMenuItem } from '@nuxt/ui'
import { computed } from 'vue'

const props = defineProps<{
  current: EnvironmentData | null
  collapsed?: boolean
}>()

const page = usePage()

const describe = (environment: EnvironmentData): string =>
  [environment.scope === 'rootful' ? 'Rootful' : 'Rootless', environment.podman ? `Podman ${environment.podman}` : null]
    .filter(Boolean)
    .join(' · ')

const items = computed<DropdownMenuItem[][]>(() => [
  (page.props.environments ?? []).map((environment) => ({
    label: environment.name,
    description: describe(environment),
    icon: environment.is_local ? 'i-lucide-house' : 'i-lucide-server',
    to: applications.url(environment.id),
    active: environment.id === props.current?.id,
  })),
  [{ label: 'Manage environments', icon: 'i-lucide-settings-2', to: environments.url() }],
])
</script>

<template>
  <UDropdownMenu
    :items="items"
    :content="{ align: 'start', collisionPadding: 12 }"
    :ui="{ content: 'w-(--reka-dropdown-menu-trigger-width) min-w-56' }"
  >
    <UButton
      color="neutral"
      variant="outline"
      block
      :square="collapsed"
      class="data-[state=open]:bg-elevated"
      trailing-icon="i-lucide-chevrons-up-down"
      :ui="{ trailingIcon: 'text-dimmed' }"
    >
      <span
        class="size-2 shrink-0 rounded-full"
        :class="current?.last_seen_at ? 'bg-success' : 'bg-muted'"
        aria-hidden="true"
      />
      <span
        v-if="!collapsed"
        class="flex min-w-0 flex-1 flex-col text-left"
      >
        <span class="truncate font-medium text-highlighted">{{ current?.name ?? 'Choose environment' }}</span>
        <span
          v-if="current"
          class="truncate text-xs text-muted"
          >{{ describe(current) }}</span
        >
      </span>
    </UButton>
  </UDropdownMenu>
</template>
