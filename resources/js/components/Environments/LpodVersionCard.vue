<script setup lang="ts">
import { update } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentLpodController'
import type { EnvironmentData, LpodVersionData } from '@/types'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  lpod: LpodVersionData
}>()

const isUpdating = ref(false)

const updateLpod = (): void => {
  router.put(
    update.url(props.environment.id),
    {},
    {
      preserveScroll: true,
      onStart: () => (isUpdating.value = true),
      onFinish: () => (isUpdating.value = false),
    },
  )
}
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">lpod</h2>
      <p class="text-sm text-muted">
        Pods manages this host through lpod, installed for {{ environment.user }}. Each user on a host has their own.
      </p>
    </template>

    <div class="flex items-start justify-between gap-4 max-sm:flex-col">
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt class="text-muted">Installed</dt>
        <dd class="font-mono">{{ lpod.installed ?? 'unknown' }}</dd>

        <dt class="text-muted">Pods needs</dt>
        <dd class="font-mono">{{ lpod.required }}</dd>
      </dl>

      <UButton
        v-if="lpod.update_available"
        :label="`Update to ${lpod.required}`"
        icon="i-lucide-download"
        :loading="isUpdating"
        @click="updateLpod"
      />
      <span
        v-else
        class="text-sm text-muted"
        >Up to date</span
      >
    </div>
  </UCard>
</template>
