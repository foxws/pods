<script setup lang="ts">
import { show as applicationShow } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { destroy } from '@/actions/Modules/Web/Networks/Controllers/NetworkController'
import NetworkPruneController from '@/actions/Modules/Web/Networks/Controllers/NetworkPruneController'
import HostError from '@/components/Environments/HostError.vue'
import NetworkCreateModal from '@/components/Networks/NetworkCreateModal.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import DataTable, { type DataTableFilter } from '@/components/Ui/DataTable.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import RowActions from '@/components/Ui/RowActions.vue'
import StatCard from '@/components/Ui/StatCard.vue'
import StatGrid from '@/components/Ui/StatGrid.vue'
import StateBadge from '@/components/Ui/StateBadge.vue'
import { useHostAction } from '@/composables/hostActions'
import type { EnvironmentData, HostProperty, NetworkData } from '@/types'
import { pluralize } from '@/utils/application'
import { Head, Link } from '@inertiajs/vue3'
import type { BadgeProps, DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  networks?: HostProperty<NetworkData[]>
}>()

const list = computed(() => props.networks?.data ?? [])

const isBuiltIn = (network: NetworkData): boolean =>
  network.default || network.driver === 'host' || network.driver === 'none'
const isIdle = (network: NetworkData): boolean => !isBuiltIn(network) && network.containers === 0
const idle = computed(() => list.value.filter(isIdle))

const status = (network: NetworkData): { label: string; color: BadgeProps['color'] } => {
  if (network.default) {
    return { label: 'Default', color: 'neutral' }
  }

  if (isBuiltIn(network)) {
    return { label: 'Built-in', color: 'neutral' }
  }

  return network.containers ? { label: 'Active', color: 'success' } : { label: 'Idle', color: 'warning' }
}

const filters: DataTableFilter<NetworkData>[] = [
  { label: 'All', value: 'all' },
  { label: 'Bridge', value: 'bridge', match: (network) => network.driver === 'bridge' },
  {
    label: 'Macvlan',
    value: 'macvlan',
    match: (network) => network.driver === 'macvlan' || network.driver === 'ipvlan',
  },
  { label: 'Idle', value: 'idle', match: isIdle },
]

const columns: TableColumn<NetworkData>[] = [
  { accessorKey: 'name', header: 'Network' },
  { accessorKey: 'driver', header: 'Driver' },
  { accessorKey: 'subnet', header: 'Subnet' },
  { accessorKey: 'gateway', header: 'Gateway' },
  { accessorKey: 'dns', header: 'DNS' },
  { accessorKey: 'containers', header: 'Containers' },
  { accessorKey: 'applications', header: 'Applications' },
  { id: 'status', header: 'Status' },
  { id: 'actions', header: '' },
]

const isCreating = ref(false)
const isPruning = ref(false)
const removing = ref<NetworkData | null>(null)
const isRemoving = computed({
  get: () => removing.value !== null,
  set: (open) => {
    if (!open) {
      removing.value = null
    }
  },
})

const { send, processing } = useHostAction()

const actions = (network: NetworkData): DropdownMenuItem[] => [
  {
    label: 'Remove',
    icon: 'i-lucide-trash-2',
    color: 'error',
    disabled: isBuiltIn(network) || network.containers > 0,
    onSelect: () => {
      removing.value = network
    },
  },
]

const searchText = (network: NetworkData): string =>
  [network.name, network.driver, network.subnet, ...network.applications].join(' ')
</script>

<template>
  <Head title="Networks" />

  <UDashboardPanel id="networks">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading title="Networks">
        <template #description>
          Netavark networks on
          <span class="font-medium text-highlighted">{{ environment.hostname ?? environment.host }}</span>. Containers
          on the same network reach each other by name.
        </template>

        <template #actions>
          <UButton
            label="Prune unused"
            icon="i-lucide-brush-cleaning"
            color="neutral"
            variant="outline"
            :disabled="!idle.length"
            @click="isPruning = true"
          />
          <UButton
            label="Create network"
            icon="i-lucide-plus"
            @click="isCreating = true"
          />
        </template>
      </PageHeading>

      <StatGrid :loading="!networks">
        <StatCard
          label="Networks"
          :value="list.length"
          :hint="`${list.filter((network) => network.quadlet).length} from quadlets`"
        />
        <StatCard
          label="Containers"
          :value="list.reduce((total, network) => total + network.containers, 0)"
          hint="attached"
        />
        <StatCard
          label="Idle"
          :value="idle.length"
          :hint="pluralize(idle.length, 'network')"
          :color="idle.length ? 'warning' : undefined"
        />
        <StatCard
          label="DNS"
          :value="list.filter((network) => network.dns).length"
          hint="with aardvark-dns"
        />
      </StatGrid>

      <HostError
        v-if="networks?.error"
        :error="networks.error"
      />

      <DataTable
        :data="networks?.data"
        :columns="columns"
        :loading="!networks"
        :filters="filters"
        :search-text="searchText"
        :row-key="(network) => network.name"
        search-placeholder="Filter networks"
        empty="No networks match this filter."
        label="Networks"
      >
        <template #item="{ row }">
          <div class="flex min-h-16 items-center gap-3 px-3 py-2.5">
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="truncate font-mono text-sm text-highlighted">{{ row.name }}</span>
              <span class="truncate text-xs text-muted">{{ row.driver }} · {{ row.subnet ?? 'no subnet' }}</span>
            </span>
            <StateBadge v-bind="status(row)" />
          </div>
        </template>

        <template #name-cell="{ row }">
          <span class="flex flex-col">
            <span class="font-mono text-sm text-highlighted">{{ row.original.name }}</span>
            <span class="font-mono text-xs text-muted">{{
              row.original.quadlet ?? (row.original.default ? 'default' : 'built-in')
            }}</span>
          </span>
        </template>

        <template #subnet-cell="{ row }">
          <span class="font-mono text-xs">{{ row.original.subnet ?? '–' }}</span>
        </template>

        <template #gateway-cell="{ row }">
          <span class="font-mono text-xs">{{ row.original.gateway ?? '–' }}</span>
        </template>

        <template #dns-cell="{ row }">
          <span class="text-muted">{{ row.original.dns ? 'aardvark-dns' : 'off' }}</span>
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

        <template #status-cell="{ row }">
          <StateBadge v-bind="status(row.original)" />
        </template>

        <template #actions-cell="{ row }">
          <RowActions
            :items="actions(row.original)"
            :label="`Actions for ${row.original.name}`"
          />
        </template>
      </DataTable>

      <NetworkCreateModal
        v-model:open="isCreating"
        :environment="environment"
      />

      <ConfirmModal
        v-model:open="isPruning"
        title="Prune unused networks"
        description="Removes networks that no container is attached to."
        :confirm-label="`Prune ${pluralize(idle.length, 'network')}`"
        :processing="processing"
        @confirm="(close) => send('post', NetworkPruneController.url(environment.id), {}, close)"
      >
        <ul class="flex flex-col divide-y divide-default rounded-lg border border-default text-sm">
          <li
            v-for="network in idle"
            :key="network.name"
            class="flex justify-between px-3 py-2"
          >
            <span class="font-mono text-highlighted">{{ network.name }}</span>
            <span class="text-muted">{{ network.applications.join(', ') }}</span>
          </li>
        </ul>
      </ConfirmModal>

      <ConfirmModal
        v-model:open="isRemoving"
        :title="`Remove ${removing?.name ?? 'network'}?`"
        description="Podman deletes the network. Containers that list it in Network= won't start until it exists again."
        confirm-label="Remove network"
        :processing="processing"
        @confirm="(close) => removing && send('delete', destroy.url([environment.id, removing.name]), {}, close)"
      />
    </template>
  </UDashboardPanel>
</template>
