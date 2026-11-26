<script setup lang="ts">
import { destroy } from '@/actions/Modules/Web/Volumes/Controllers/VolumeExportController'
import StateBadge from '@/components/Ui/StateBadge.vue'
import { useHostAction } from '@/composables/hostActions'
import type { EnvironmentData, VolumeExportData, VolumeExportStatus } from '@/types'
import { formatBytes, formatRelative } from '@/utils/format'
import { optionLabel } from '@/utils/options'
import type { BadgeProps } from '@nuxt/ui'
import { usePage } from '@inertiajs/vue3'

defineProps<{
  environment: EnvironmentData
  exports: VolumeExportData[]
}>()

const statuses: Record<VolumeExportStatus, { color: BadgeProps['color']; busy?: boolean }> = {
  pending: { color: 'neutral', busy: true },
  running: { color: 'info', busy: true },
  completed: { color: 'success' },
  failed: { color: 'error' },
}

const statusLabel = (status: VolumeExportStatus): string => optionLabel(usePage().props.volumeExportStatuses, status)

const { send, processing } = useHostAction()
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">Exports</h2>
      <p class="text-sm text-muted">
        Archives of volumes, kept on Pods' own data volume. Pods keeps the newest few of each volume.
      </p>
    </template>

    <ul
      v-if="exports.length"
      class="flex flex-col divide-y divide-default text-sm"
    >
      <li
        v-for="item in exports"
        :key="item.id"
        class="flex flex-wrap items-center gap-3 py-2.5 first:pt-0 last:pb-0"
      >
        <span class="flex min-w-0 flex-1 flex-col">
          <span class="truncate font-mono text-highlighted">{{ item.volume }}</span>
          <span class="text-xs text-muted">
            {{ formatRelative(item.created_at) }}
            <template v-if="item.size !== null"> · {{ formatBytes(item.size) }}</template>
            <template v-if="item.stop_applications"> · applications stopped meanwhile</template>
          </span>
          <span
            v-if="item.error"
            class="text-xs break-words text-error"
            >{{ item.error }}</span
          >
        </span>
        <StateBadge
          v-bind="statuses[item.status]"
          :label="statusLabel(item.status)"
        />
        <UButton
          v-if="item.download_url"
          label="Download"
          icon="i-lucide-download"
          color="neutral"
          variant="outline"
          size="xs"
          :to="item.download_url"
          external
        />
        <UButton
          v-if="item.status === 'completed' || item.status === 'failed'"
          icon="i-lucide-trash-2"
          color="error"
          variant="ghost"
          size="xs"
          :aria-label="`Remove the export of ${item.volume}`"
          :disabled="processing"
          @click="send('delete', destroy.url([environment.id, item.id]), {}, undefined, ['exports'])"
        />
      </li>
    </ul>
    <p
      v-else
      class="text-sm text-muted"
    >
      No exports yet. Export a volume from its row's actions.
    </p>
  </UCard>
</template>
