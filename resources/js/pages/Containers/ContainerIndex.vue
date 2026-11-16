<script setup lang="ts">
import { show as applicationShow } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { create, show } from '@/actions/Modules/Web/Containers/Controllers/ContainerController'
import ContainerOperationController from '@/actions/Modules/Web/Containers/Controllers/ContainerOperationController'
import HostError from '@/components/Environments/HostError.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import DataTable, { type DataTableFilter } from '@/components/Ui/DataTable.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import RowActions from '@/components/Ui/RowActions.vue'
import StatCard from '@/components/Ui/StatCard.vue'
import StatGrid from '@/components/Ui/StatGrid.vue'
import StateBadge from '@/components/Ui/StateBadge.vue'
import { useHostPolling } from '@/composables/applications'
import { useHostAction } from '@/composables/hostActions'
import type { ContainerOperation, EnvironmentData, HostContainerData, HostProperty, HostStatsData } from '@/types'
import { containerState, shortImage } from '@/utils/containers'
import { formatBytes, formatPort, formatUptime } from '@/utils/format'
import { Head, Link } from '@inertiajs/vue3'
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { computed } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  containers?: HostProperty<HostContainerData[]>
  host?: HostProperty<HostStatsData>
}>()

const list = computed(() => props.containers?.data ?? [])

const running = computed(() => list.value.filter((container) => container.state === 'running'))
const unmanaged = computed(() => list.value.filter((container) => !container.quadlet))

const cpu = computed(() => running.value.reduce((total, container) => total + (container.cpu ?? 0), 0))
const memory = computed(() => running.value.reduce((total, container) => total + (container.memory ?? 0), 0))

const isBusy = computed(() => list.value.some((container) => containerState(container).busy))

useHostPolling(['containers', 'host'], isBusy)

const filters: DataTableFilter<HostContainerData>[] = [
  { label: 'All', value: 'all' },
  { label: 'Running', value: 'running', match: (container) => container.state === 'running' },
  { label: 'Stopped', value: 'stopped', match: (container) => ['stopped', 'created'].includes(container.state) },
  { label: 'Exited', value: 'exited', match: (container) => container.state === 'exited' },
  { label: 'Unmanaged', value: 'unmanaged', match: (container) => !container.quadlet },
]

const columns: TableColumn<HostContainerData>[] = [
  { accessorKey: 'name', header: 'Container' },
  { accessorKey: 'state', header: 'Status' },
  { accessorKey: 'application', header: 'Application' },
  { accessorKey: 'image', header: 'Image' },
  { accessorKey: 'ports', header: 'Ports' },
  { accessorKey: 'cpu', header: 'CPU' },
  { accessorKey: 'memory', header: 'Memory' },
  { accessorKey: 'started_at', header: 'Uptime' },
  { id: 'actions', header: '' },
]

const { send } = useHostAction()

const operate = (container: HostContainerData, operation: ContainerOperation): void =>
  send('post', ContainerOperationController.url([props.environment.id, container.name, operation]))

const actions = (container: HostContainerData): DropdownMenuItem[][] => [
  [{ label: 'Open', icon: 'i-lucide-square-arrow-out-up-right', to: show.url([props.environment.id, container.name]) }],
  [
    {
      label: 'Start',
      icon: 'i-lucide-play',
      disabled: container.state === 'running',
      onSelect: () => operate(container, 'start'),
    },
    {
      label: 'Restart',
      icon: 'i-lucide-rotate-cw',
      disabled: container.state !== 'running',
      onSelect: () => operate(container, 'restart'),
    },
    {
      label: 'Stop',
      icon: 'i-lucide-square',
      color: 'error',
      disabled: container.state !== 'running',
      onSelect: () => operate(container, 'stop'),
    },
  ],
]

const searchText = (container: HostContainerData): string =>
  [container.name, container.image, container.application].filter(Boolean).join(' ')
</script>

<template>
  <Head title="Containers" />

  <UDashboardPanel id="containers">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading title="Containers">
        <template #description>
          Every container on
          <span class="font-medium text-highlighted">{{ environment.hostname ?? environment.host }}</span>, including
          ones started outside Quadlet.
        </template>

        <template #actions>
          <UButton
            icon="i-lucide-plus"
            label="Add container"
            :to="create.url(environment.id)"
          />
        </template>
      </PageHeading>

      <StatGrid
        v-if="!containers?.error"
        :loading="!containers"
      >
        <StatCard
          label="Running"
          :value="running.length"
          :hint="`of ${list.length}`"
        />
        <StatCard
          label="CPU"
          :value="`${cpu.toFixed(1)}%`"
          hint="across containers"
        />
        <StatCard
          label="Memory"
          :value="formatBytes(memory)"
          :hint="host?.data ? `of ${formatBytes(host.data.memory_total)}` : undefined"
          :progress="host?.data?.memory_total ? Math.round((memory / host.data.memory_total) * 100) : undefined"
        />
        <StatCard
          label="Unmanaged"
          :value="unmanaged.length"
          hint="not from a quadlet"
          :color="unmanaged.length ? 'warning' : undefined"
        />
      </StatGrid>

      <HostError
        v-if="containers?.error"
        :error="containers.error"
      />

      <DataTable
        :data="containers?.data"
        :columns="columns"
        :loading="!containers"
        :filters="filters"
        :search-text="searchText"
        :row-key="(container) => container.id"
        search-placeholder="Filter containers"
        empty="No containers match this filter."
        label="Containers"
      >
        <template #item="{ row }">
          <Link
            :href="show.url([environment.id, row.name])"
            class="flex min-h-16 items-center gap-3 px-3 py-2.5"
          >
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="truncate font-medium text-highlighted">{{ row.name }}</span>
              <span class="truncate font-mono text-xs text-muted">{{ shortImage(row.image) }}</span>
            </span>
            <StateBadge v-bind="containerState(row)" />
          </Link>
        </template>

        <template #name-cell="{ row }">
          <Link
            :href="show.url([environment.id, row.original.name])"
            class="flex flex-col"
          >
            <span class="font-medium text-highlighted">{{ row.original.name }}</span>
            <span
              class="font-mono text-xs"
              :class="row.original.quadlet ? 'text-muted' : 'text-warning'"
              >{{ row.original.quadlet ?? 'not managed by Quadlet' }}</span
            >
          </Link>
        </template>

        <template #state-cell="{ row }">
          <StateBadge v-bind="containerState(row.original)" />
        </template>

        <template #application-cell="{ row }">
          <Link
            v-if="row.original.application"
            :href="applicationShow.url([environment.id, row.original.application])"
            class="text-primary"
            >{{ row.original.application }}</Link
          >
          <span
            v-else
            class="text-muted"
            >–</span
          >
        </template>

        <template #image-cell="{ row }">
          <span class="font-mono text-xs">{{ shortImage(row.original.image) }}</span>
        </template>

        <template #ports-cell="{ row }">
          <span class="text-muted">{{ row.original.ports.map(formatPort).join(', ') || '–' }}</span>
        </template>

        <template #cpu-cell="{ row }">
          <span class="text-muted">{{ row.original.cpu === null ? '–' : `${row.original.cpu}%` }}</span>
        </template>

        <template #memory-cell="{ row }">
          <span class="text-muted">{{ formatBytes(row.original.memory) }}</span>
        </template>

        <template #started_at-cell="{ row }">
          <span class="text-muted">{{
            row.original.state === 'running' ? formatUptime(row.original.started_at) : '–'
          }}</span>
        </template>

        <template #actions-cell="{ row }">
          <RowActions
            :items="actions(row.original)"
            :label="`Actions for ${row.original.name}`"
          />
        </template>

        <template #footer>
          <UAlert
            v-for="container in unmanaged"
            :key="container.id"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            :title="`${container.name} isn't managed by Quadlet`"
            description="It was started with podman run, so it won't come back after a reboot. Add it as a container to keep it."
            :actions="[
              {
                label: 'Add as container',
                color: 'warning',
                variant: 'outline',
                to: create.url(environment.id, { query: { image: container.image ?? '' } }),
              },
            ]"
          />
        </template>
      </DataTable>
    </template>
  </UDashboardPanel>
</template>
