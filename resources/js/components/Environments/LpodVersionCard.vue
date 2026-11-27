<script setup lang="ts">
import CardHeader from '@/components/Ui/CardHeader.vue'
import { useLpodActions } from '@/composables/lpod'
import type { EnvironmentData, LpodVersionData } from '@/types'
import { formatRelative } from '@/utils/format'

const props = defineProps<{
  environment: EnvironmentData
  lpod: LpodVersionData
}>()

const { updateLpod, checkAgain, isUpdating, isChecking } = useLpodActions(() => props.environment)
</script>

<template>
  <UCard>
    <template #header>
      <CardHeader title="lpod">
        <template #description>
          Pods manages this environment through lpod, installed for {{ environment.user }}. Each user on a host has
          their own. Pods checks the versions daily, or when you check again.
        </template>
      </CardHeader>
    </template>

    <div class="flex items-start justify-between gap-4 max-sm:flex-col">
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt class="text-muted">Installed</dt>
        <dd class="font-mono">{{ lpod.installed ?? 'unknown' }}</dd>

        <dt class="text-muted">Pods needs</dt>
        <dd class="font-mono">{{ lpod.required }}</dd>

        <dt class="text-muted">Podman</dt>
        <dd class="font-mono">{{ environment.podman ?? 'unknown' }}</dd>

        <dt class="text-muted">Checked</dt>
        <dd>{{ environment.last_seen_at ? formatRelative(environment.last_seen_at) : 'never' }}</dd>
      </dl>

      <div class="flex flex-wrap items-center gap-2">
        <UButton
          label="Check again"
          icon="i-lucide-refresh-cw"
          color="neutral"
          variant="outline"
          :loading="isChecking"
          :disabled="isUpdating"
          @click="checkAgain"
        />
        <UButton
          v-if="lpod.update_available"
          :label="`Update to ${lpod.required}`"
          icon="i-lucide-download"
          :loading="isUpdating"
          :disabled="isChecking"
          @click="updateLpod"
        />
        <span
          v-else
          class="text-sm text-muted"
          >Up to date</span
        >
      </div>
    </div>
  </UCard>
</template>
