<script setup lang="ts">
import { show as applicationShow } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import HostError from '@/components/Environments/HostError.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import DataTable, { type DataTableFilter } from '@/components/Ui/DataTable.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import StatCard from '@/components/Ui/StatCard.vue'
import StatGrid from '@/components/Ui/StatGrid.vue'
import StateBadge from '@/components/Ui/StateBadge.vue'
import { useHostPolling } from '@/composables/applications'
import type { EnvironmentData, HostProperty, PodData } from '@/types'
import { pluralize } from '@/utils/application'
import { formatPort, formatRelative } from '@/utils/format'
import { Head, Link } from '@inertiajs/vue3'
import type { TableColumn } from '@nuxt/ui'
import { computed } from 'vue'
import { timeOf, type SortableColumn } from '@/utils/sorting'
import { isStoppedPod, podContainerCount, podStatus } from '@/utils/pods'

const props = defineProps<{
  environment: EnvironmentData
  pods?: HostProperty<PodData[]>
}>()

const list = computed(() => props.pods?.data ?? [])

const running = computed(() => list.value.filter((pod) => pod.status === 'running'))
const degraded = computed(() => list.value.filter((pod) => pod.status === 'degraded'))
const unmanaged = computed(() => list.value.filter((pod) => !pod.quadlet))

useHostPolling(['pods'], () => false)

const filters: DataTableFilter<PodData>[] = [
  { label: 'All', value: 'all' },
  { label: 'Running', value: 'running', match: (pod) => pod.status === 'running' },
  { label: 'Degraded', value: 'degraded', match: (pod) => pod.status === 'degraded' },
  { label: 'Stopped', value: 'stopped', match: isStoppedPod },
  { label: 'Unmanaged', value: 'unmanaged', match: (pod) => !pod.quadlet },
]

const columns: TableColumn<PodData>[] = [
  { accessorKey: 'name', header: 'Pod' },
  { id: 'status', header: 'Status' },
  { accessorKey: 'application', header: 'Application' },
  { accessorKey: 'containers', header: 'Containers' },
  { accessorKey: 'ports', header: 'Ports' },
  { accessorKey: 'networks', header: 'Networks' },
  { accessorKey: 'created_at', header: 'Created' },
]

const sortable: SortableColumn<PodData>[] = [
  { column: 'name', label: 'Pod', value: (row) => row.name },
  { column: 'status', label: 'Status', value: (row) => row.status },
  { column: 'application', label: 'Application', value: (row) => row.application },
  { column: 'containers', label: 'Containers', value: (row) => row.containers, descendingFirst: true },
  { column: 'created_at', label: 'Created', value: (row) => timeOf(row.created_at), descendingFirst: true },
]

const searchText = (pod: PodData): string =>
  [pod.name, pod.application, pod.quadlet, ...pod.networks].filter(Boolean).join(' ')
</script>

<template>
  <Head title="Pods" />

  <UDashboardPanel id="pods">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading title="Pods">
        <template #description>
          Pods on
          <span class="font-medium text-highlighted">{{ environment.hostname ?? environment.host }}</span>. Their
          containers share a network namespace and publish ports through the infra container. Start and stop them
          through their application.
        </template>
      </PageHeading>

      <StatGrid
        v-if="!pods?.error"
        :loading="!pods"
      >
        <StatCard
          label="Pods"
          :value="list.length"
          :hint="`${list.length - unmanaged.length} from quadlets`"
        />
        <StatCard
          label="Running"
          :value="running.length"
          :hint="`of ${pluralize(list.length, 'pod')}`"
        />
        <StatCard
          label="Degraded"
          :value="degraded.length"
          hint="some containers stopped"
          :color="degraded.length ? 'warning' : undefined"
        />
        <StatCard
          label="Containers"
          :value="list.reduce((total, pod) => total + pod.containers, 0)"
          hint="in pods"
        />
      </StatGrid>

      <HostError
        v-if="pods?.error"
        :error="pods.error"
      />

      <DataTable
        :data="pods?.data"
        :columns="columns"
        :sortable="sortable"
        :loading="!pods"
        :filters="filters"
        :search-text="searchText"
        :row-key="(pod) => pod.id"
        search-placeholder="Filter pods"
        empty="No pods match this filter."
        label="Pods"
      >
        <template #item="{ row }">
          <component
            :is="row.application ? Link : 'div'"
            :href="row.application ? applicationShow.url([environment.id, row.application]) : undefined"
            class="flex min-h-16 items-center gap-3 px-3 py-2.5"
          >
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="truncate font-mono text-sm text-highlighted">{{ row.name }}</span>
              <span class="truncate text-xs text-muted"
                >{{ pluralize(row.containers, 'container') }} ·
                {{ row.ports.map(formatPort).join(', ') || 'no published ports' }}</span
              >
            </span>
            <StateBadge v-bind="podStatus(row)" />
          </component>
        </template>

        <template #name-cell="{ row }">
          <span class="flex flex-col">
            <span class="font-mono text-sm text-highlighted">{{ row.original.name }}</span>
            <span class="font-mono text-xs text-muted">{{ row.original.quadlet ?? 'not managed by Quadlet' }}</span>
          </span>
        </template>

        <template #status-cell="{ row }">
          <StateBadge v-bind="podStatus(row.original)" />
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

        <template #containers-cell="{ row }">
          {{ podContainerCount(row.original) }}
        </template>

        <template #ports-cell="{ row }">
          <span class="text-muted">{{ row.original.ports.map(formatPort).join(', ') || '–' }}</span>
        </template>

        <template #networks-cell="{ row }">
          <span class="font-mono text-xs text-muted">{{ row.original.networks.join(', ') || '–' }}</span>
        </template>

        <template #created_at-cell="{ row }">
          <span class="text-muted">{{ formatRelative(row.original.created_at) }}</span>
        </template>

        <template #footer>
          <UAlert
            v-for="pod in unmanaged"
            :key="pod.id"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            :title="`${pod.name} isn't managed by Quadlet`"
            description="It was created with podman pod create, so it won't come back after a reboot. Install its containers as an application to keep them."
          />
        </template>
      </DataTable>
    </template>
  </UDashboardPanel>
</template>
