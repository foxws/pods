<script setup lang="ts">
import { show as applicationShow } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { show as containerShow } from '@/actions/Modules/Web/Containers/Controllers/ContainerController'
import { destroy } from '@/actions/Modules/Web/Volumes/Controllers/VolumeController'
import VolumePruneController from '@/actions/Modules/Web/Volumes/Controllers/VolumePruneController'
import HostError from '@/components/Environments/HostError.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import DataTable, { type DataTableFilter } from '@/components/Ui/DataTable.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import RowActions from '@/components/Ui/RowActions.vue'
import StatCard from '@/components/Ui/StatCard.vue'
import StatGrid from '@/components/Ui/StatGrid.vue'
import StateBadge from '@/components/Ui/StateBadge.vue'
import VolumeCreateModal from '@/components/Volumes/VolumeCreateModal.vue'
import VolumeExportModal from '@/components/Volumes/VolumeExportModal.vue'
import VolumeExportsCard from '@/components/Volumes/VolumeExportsCard.vue'
import { useHostAction } from '@/composables/hostActions'
import { useHostReload } from '@/composables/polling'
import type { EnvironmentData, HostProperty, HostVolumeData, VolumeExportData } from '@/types'
import { pluralize } from '@/utils/application'
import { formatBytes } from '@/utils/format'
import { Head, Link } from '@inertiajs/vue3'
import type { BadgeProps, DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { computed, ref, watch } from 'vue'
import type { SortableColumn } from '@/utils/sorting'

const props = defineProps<{
  environment: EnvironmentData
  volumes?: HostProperty<HostVolumeData[]>
  exports: VolumeExportData[]
}>()

const list = computed(() => props.volumes?.data ?? [])

const isUnused = (volume: HostVolumeData): boolean => volume.containers.length === 0
const unused = computed(() => list.value.filter(isUnused))
const remote = computed(() => list.value.filter((volume) => volume.type === 'nfs'))

const types: Record<string, { label: string; color: BadgeProps['color'] }> = {
  named: { label: 'Named', color: 'neutral' },
  bind: { label: 'Bind', color: 'info' },
  device: { label: 'Host folder', color: 'info' },
  nfs: { label: 'NFS', color: 'primary' },
  anonymous: { label: 'Anonymous', color: 'warning' },
}

const typeOf = (volume: HostVolumeData) =>
  isUnused(volume) ? { label: 'Unused', color: 'warning' as const } : (types[volume.type] ?? types.named!)

const displayName = (volume: HostVolumeData): string =>
  volume.type === 'anonymous' ? `${volume.name.slice(0, 12)}…` : volume.name

const filters: DataTableFilter<HostVolumeData>[] = [
  { label: 'All', value: 'all' },
  { label: 'Named', value: 'named', match: (volume) => volume.type === 'named' },
  { label: 'Bind', value: 'bind', match: (volume) => volume.type === 'bind' },
  { label: 'NFS', value: 'nfs', match: (volume) => volume.type === 'nfs' },
  { label: 'Unused', value: 'unused', match: isUnused },
]

const columns: TableColumn<HostVolumeData>[] = [
  { accessorKey: 'name', header: 'Volume' },
  { accessorKey: 'driver', header: 'Driver' },
  { accessorKey: 'mountpoint', header: 'Mount point' },
  { accessorKey: 'size', header: 'Size' },
  { accessorKey: 'containers', header: 'Containers' },
  { accessorKey: 'applications', header: 'Application' },
  { accessorKey: 'type', header: 'Type' },
  { id: 'actions', header: '' },
]

const sortable: SortableColumn<HostVolumeData>[] = [
  { column: 'name', label: 'Volume', value: (row) => row.name },
  { column: 'driver', label: 'Driver', value: (row) => row.driver },
  { column: 'size', label: 'Size', value: (row) => row.size, descendingFirst: true },
  { column: 'containers', label: 'Containers', value: (row) => row.containers.length, descendingFirst: true },
  { column: 'applications', label: 'Application', value: (row) => row.applications[0] },
  { column: 'type', label: 'Type', value: (row) => row.type },
]

const isCreating = ref(false)
const isPruning = ref(false)
const removing = ref<HostVolumeData | null>(null)
const isRemoving = computed({
  get: () => removing.value !== null,
  set: (open) => {
    if (!open) {
      removing.value = null
    }
  },
})

const { send, processing } = useHostAction()

/**
 * Only volumes in Podman's own storage: one backed by a folder on the host or NFS would be all of it.
 * Mirrors HostVolumeData::isExportable().
 */
const isExportable = (volume: HostVolumeData): boolean => volume.type === 'named' || volume.type === 'anonymous'

const exporting = ref<{ volume: string | null; application: string | null; applications: string[] } | null>(null)
const isExporting = computed({
  get: () => exporting.value !== null,
  set: (open) => {
    if (!open) {
      exporting.value = null
    }
  },
})

const exportItems = (volume: HostVolumeData): DropdownMenuItem[] => {
  if (!isExportable(volume)) {
    return []
  }

  return [
    {
      label: 'Export',
      icon: 'i-lucide-archive',
      onSelect: () => {
        exporting.value = { volume: volume.name, application: null, applications: volume.applications }
      },
    },
    ...volume.applications.map((application) => ({
      label: `Export every volume of ${application}`,
      icon: 'i-lucide-archive',
      onSelect: () => {
        exporting.value = { volume: null, application, applications: [application] }
      },
    })),
  ]
}

const hasUnfinishedExports = computed(() =>
  props.exports.some((item) => item.status === 'pending' || item.status === 'running'),
)

// The notification reloads the list when Reverb is up; this also catches it when it isn't.
const exportPolling = useHostReload(['exports'], 5000, { immediate: false })

watch(hasUnfinishedExports, (unfinished) => (unfinished ? exportPolling.resume() : exportPolling.pause()), {
  immediate: true,
})

const actions = (volume: HostVolumeData): DropdownMenuItem[] => [
  ...exportItems(volume),
  {
    label: 'Remove',
    icon: 'i-lucide-trash-2',
    color: 'error',
    disabled: volume.type === 'bind' || !isUnused(volume),
    onSelect: () => {
      removing.value = volume
    },
  },
]

const searchText = (volume: HostVolumeData): string =>
  [volume.name, volume.mountpoint, ...volume.containers, ...volume.applications].join(' ')
</script>

<template>
  <Head title="Volumes" />

  <UDashboardPanel id="volumes">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading
        title="Volumes"
        description="Named, bind and network volumes used by your containers."
      >
        <template #actions>
          <UButton
            label="Prune unused"
            icon="i-lucide-brush-cleaning"
            color="neutral"
            variant="outline"
            :disabled="!unused.length"
            @click="isPruning = true"
          />
          <UButton
            label="Create volume"
            icon="i-lucide-plus"
            @click="isCreating = true"
          />
        </template>
      </PageHeading>

      <StatGrid
        v-if="!volumes?.error"
        :loading="!volumes"
      >
        <StatCard
          label="Volumes"
          :value="list.length"
          :hint="`${list.length - unused.length} in use`"
        />
        <StatCard
          label="From quadlets"
          :value="list.filter((volume) => volume.quadlet).length"
          hint="recreated on boot"
        />
        <StatCard
          label="Unused"
          :value="unused.length"
          :hint="pluralize(unused.length, 'volume')"
          :color="unused.length ? 'warning' : undefined"
        />
        <StatCard
          label="Remote"
          :value="remote.length"
          hint="NFS mounts"
        />
      </StatGrid>

      <HostError
        v-if="volumes?.error"
        :error="volumes.error"
      />

      <DataTable
        :data="volumes?.data"
        :columns="columns"
        :sortable="sortable"
        :loading="!volumes"
        :filters="filters"
        :search-text="searchText"
        :row-key="(volume) => volume.name"
        search-placeholder="Filter volumes"
        empty="No volumes match this filter."
        label="Volumes"
      >
        <template #item="{ row }">
          <div class="flex min-h-16 items-center gap-3 px-3 py-2.5">
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="truncate font-mono text-sm text-highlighted">{{ displayName(row) }}</span>
              <span class="truncate text-xs text-muted">{{ row.containers.join(', ') || 'no containers' }}</span>
            </span>
            <StateBadge v-bind="typeOf(row)" />
          </div>
        </template>

        <template #name-cell="{ row }">
          <span class="flex flex-col">
            <span class="font-mono text-sm text-highlighted">{{ displayName(row.original) }}</span>
            <span class="font-mono text-xs text-muted">{{ row.original.quadlet ?? row.original.type }}</span>
          </span>
        </template>

        <template #driver-cell="{ row }">
          <span class="text-muted">{{ row.original.driver }}</span>
        </template>

        <template #mountpoint-cell="{ row }">
          <span class="block max-w-xs truncate font-mono text-xs text-muted">{{ row.original.mountpoint ?? '–' }}</span>
        </template>

        <template #size-cell="{ row }">
          <span class="text-muted">{{ formatBytes(row.original.size) }}</span>
        </template>

        <template #containers-cell="{ row }">
          <span
            v-if="row.original.containers.length"
            class="flex flex-wrap gap-x-2"
          >
            <Link
              v-for="container in row.original.containers"
              :key="container"
              :href="containerShow.url([environment.id, container])"
              class="font-mono text-xs text-highlighted"
              >{{ container }}</Link
            >
          </span>
          <span
            v-else
            class="text-muted"
            >–</span
          >
        </template>

        <template #applications-cell="{ row }">
          <span
            v-if="row.original.applications.length"
            class="flex flex-wrap gap-x-2"
          >
            <Link
              v-for="application in row.original.applications"
              :key="application"
              :href="applicationShow.url([environment.id, application])"
              class="text-primary"
              >{{ application }}</Link
            >
          </span>
          <span
            v-else
            class="text-muted"
            >–</span
          >
        </template>

        <template #type-cell="{ row }">
          <StateBadge v-bind="typeOf(row.original)" />
        </template>

        <template #actions-cell="{ row }">
          <RowActions
            :items="actions(row.original)"
            :label="`Actions for ${displayName(row.original)}`"
          />
        </template>

        <template #footer>
          <p class="flex items-start gap-2 text-sm text-muted">
            <UIcon
              name="i-lucide-info"
              class="mt-0.5 size-4 shrink-0"
            />
            Volumes from a .volume quadlet are recreated on start. Removing one here deletes its data, but the quadlet
            file stays.
          </p>
        </template>
      </DataTable>

      <VolumeExportsCard
        :environment="environment"
        :exports="exports"
        class="mt-6"
      />

      <VolumeExportModal
        v-model:open="isExporting"
        :environment="environment"
        :volume="exporting?.volume"
        :application="exporting?.application"
        :applications="exporting?.applications ?? []"
      />

      <VolumeCreateModal
        v-model:open="isCreating"
        :environment="environment"
      />

      <ConfirmModal
        v-model:open="isPruning"
        title="Prune unused volumes"
        description="Removes every volume that no container uses, with its data. This can't be undone."
        :confirm-label="`Prune ${pluralize(unused.length, 'volume')}`"
        :processing="processing"
        confirm-text="prune"
        @confirm="(close) => send('post', VolumePruneController.url(environment.id), {}, close)"
      >
        <ul class="flex flex-col divide-y divide-default rounded-lg border border-default text-sm">
          <li
            v-for="volume in unused"
            :key="volume.name"
            class="truncate px-3 py-2 font-mono text-highlighted"
          >
            {{ displayName(volume) }}
          </li>
        </ul>
      </ConfirmModal>

      <ConfirmModal
        v-model:open="isRemoving"
        :title="`Remove ${removing ? displayName(removing) : 'volume'}?`"
        description="Deletes the volume and its data. This can't be undone."
        confirm-label="Remove volume"
        :processing="processing"
        :confirm-text="removing?.type === 'anonymous' ? undefined : removing?.name"
        @confirm="(close) => removing && send('delete', destroy.url([environment.id, removing.name]), {}, close)"
      />
    </template>
  </UDashboardPanel>
</template>
