<script setup lang="ts">
import { update } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentLpodController'
import { store as verify } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentVerificationController'
import { useHostAction } from '@/composables/hostActions'
import type { EnvironmentData, LpodVersionData } from '@/types'
import { formatRelative } from '@/utils/format'

const props = defineProps<{
  environment: EnvironmentData
  lpod: LpodVersionData
}>()

// Both verify the host, which stores its facts (shown here and in the host switcher).
const verifiedProps = ['environment', 'lpod', 'environments']

const { send: sendUpdate, processing: isUpdating } = useHostAction()
const { send: sendCheck, processing: isChecking } = useHostAction()

const updateLpod = (): void => sendUpdate('put', update.url(props.environment.id), {}, undefined, verifiedProps)

const checkAgain = (): void => sendCheck('post', verify.url(props.environment.id), {}, undefined, verifiedProps)
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">lpod</h2>
      <p class="text-sm text-muted">
        Pods manages this host through lpod, installed for {{ environment.user }}. Each user on a host has their own.
        Pods checks the versions daily, or when you check again.
      </p>
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
