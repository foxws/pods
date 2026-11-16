<script setup lang="ts">
import { create, show } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import ImageUpdateController from '@/actions/Modules/Web/Images/Controllers/ImageUpdateController'
import ApplicationForceStopModal from '@/components/Applications/ApplicationForceStopModal.vue'
import ApplicationRemoveModal from '@/components/Applications/ApplicationRemoveModal.vue'
import { index as secretsIndex } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import UnitStatusBadge from '@/components/Applications/UnitStatusBadge.vue'
import HostError from '@/components/Environments/HostError.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import { useApplicationOperations, useHostPolling } from '@/composables/applications'
import { useHostAction } from '@/composables/hostActions'
import type { ApplicationData, EnvironmentData, HostProperty, HostStatsData, ImageData } from '@/types'
import { describeApplication, describeUnits, quadletName } from '@/utils/application'
import { missingSecretIn } from '@/utils/attention'
import { formatBytes, formatPort, formatRelative } from '@/utils/format'
import { applicationsWithUpdates } from '@/utils/images'
import { isTransitioning } from '@/utils/status'
import { Head, Link } from '@inertiajs/vue3'
import type { DropdownMenuItem, TableColumn, TabsItem } from '@nuxt/ui'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  applications?: HostProperty<ApplicationData[]>
  host?: HostProperty<HostStatsData>
  images?: HostProperty<ImageData[]>
}>()

const withUpdates = computed(() => new Set(applicationsWithUpdates(props.images?.data ?? [])))

type Filter = 'all' | 'running' | 'inactive' | 'failed'

const filter = ref<Filter>('all')

const list = computed(() => props.applications?.data ?? [])

const countOf = (status: Filter): number =>
  status === 'all' ? list.value.length : list.value.filter((application) => application.status === status).length

const tabs = computed<TabsItem[]>(() => [
  { label: 'All', value: 'all', badge: countOf('all') },
  { label: 'Running', value: 'running', badge: countOf('running') },
  { label: 'Stopped', value: 'inactive', badge: countOf('inactive') },
  { label: 'Failed', value: 'failed', badge: countOf('failed') },
])

const visible = computed(() =>
  filter.value === 'all' ? list.value : list.value.filter((application) => application.status === filter.value),
)

const failures = computed(() => list.value.filter((application) => application.status === 'failed'))

const isBusy = computed(() => list.value.some((application) => isTransitioning(application.status)))

useHostPolling(['applications', 'host'], isBusy)

const { run } = useApplicationOperations(() => props.environment)

const removing = ref<string | null>(null)
const isRemoving = computed({
  get: () => removing.value !== null,
  set: (open) => {
    if (!open) {
      removing.value = null
    }
  },
})

const forceStopping = ref<string | null>(null)
const isForceStopping = computed({
  get: () => forceStopping.value !== null,
  set: (open) => {
    if (!open) {
      forceStopping.value = null
    }
  },
})

const { send, processing } = useHostAction()

const actions = (application: ApplicationData): DropdownMenuItem[][] => [
  [
    {
      label: 'Open',
      icon: 'i-lucide-square-arrow-out-up-right',
      to: show.url([props.environment.id, application.name]),
    },
  ],
  [
    {
      label: 'Start',
      icon: 'i-lucide-play',
      disabled: application.status === 'running',
      onSelect: () => run(application.name, 'up'),
    },
    {
      label: 'Restart',
      icon: 'i-lucide-rotate-cw',
      disabled: application.status !== 'running',
      onSelect: () => run(application.name, 'restart'),
    },
    {
      label: 'Pull and redeploy',
      icon: 'i-lucide-download',
      disabled: isTransitioning(application.status),
      onSelect: () => run(application.name, 'redeploy'),
    },
    {
      label: 'Stop',
      icon: 'i-lucide-square',
      color: 'error',
      disabled: application.status === 'inactive',
      onSelect: () => run(application.name, 'down'),
    },
    {
      label: 'Force stop',
      icon: 'i-lucide-octagon-x',
      color: 'error',
      disabled: application.status === 'inactive' || application.status === 'unknown',
      onSelect: () => {
        forceStopping.value = application.name
      },
    },
  ],
  [
    {
      label: 'Remove application',
      icon: 'i-lucide-trash-2',
      color: 'error',
      onSelect: () => {
        removing.value = application.name
      },
    },
  ],
]

const columns: TableColumn<ApplicationData>[] = [
  { accessorKey: 'name', header: 'Application' },
  { accessorKey: 'status', header: 'Status' },
  { id: 'units', header: 'Units' },
  { accessorKey: 'image', header: 'Image' },
  { accessorKey: 'ports', header: 'Ports' },
  { accessorKey: 'memory', header: 'Memory' },
  { id: 'actions', header: '' },
]

const percentage = (used: number, total: number): number => (total > 0 ? Math.round((used / total) * 100) : 0)
</script>

<template>
  <Head title="Applications" />

  <UDashboardPanel id="applications">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading title="Applications">
        <template #description>
          Quadlet applications installed for user
          <span class="font-medium text-highlighted">{{ environment.user }}</span>
          on
          <span class="font-medium text-highlighted">{{ environment.hostname ?? environment.host }}</span>.
        </template>

        <template #actions>
          <UButton
            label="Check for updates"
            icon="i-lucide-refresh-cw"
            color="neutral"
            variant="outline"
            :loading="processing"
            @click="send('post', ImageUpdateController.url(environment.id))"
          />
          <UButton
            icon="i-lucide-plus"
            label="Install application"
            :to="create.url(environment.id)"
          />
        </template>
      </PageHeading>

      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <template v-if="host?.data">
          <UCard variant="subtle">
            <p class="text-sm text-muted">Containers</p>
            <p class="text-2xl font-semibold text-highlighted">
              {{ host.data.containers_running }}
              <span class="text-sm font-normal text-muted">of {{ host.data.containers_total }} running</span>
            </p>
          </UCard>

          <UCard variant="subtle">
            <p class="text-sm text-muted">CPU</p>
            <p class="text-2xl font-semibold text-highlighted">
              {{ Math.round(host.data.cpu_usage) }}%
              <span class="text-sm font-normal text-muted">of {{ host.data.cpu_cores }} cores</span>
            </p>
          </UCard>

          <UCard variant="subtle">
            <p class="text-sm text-muted">Memory</p>
            <p class="text-2xl font-semibold text-highlighted">
              {{ formatBytes(host.data.memory_used) }}
              <span class="text-sm font-normal text-muted">of {{ formatBytes(host.data.memory_total) }}</span>
            </p>
            <UProgress
              :model-value="percentage(host.data.memory_used, host.data.memory_total)"
              size="xs"
              class="mt-2"
            />
          </UCard>

          <UCard variant="subtle">
            <p class="text-sm text-muted">Disk</p>
            <p class="text-2xl font-semibold text-highlighted">
              {{ formatBytes(host.data.disk_used) }}
              <span class="text-sm font-normal text-muted">of {{ formatBytes(host.data.disk_total) }}</span>
            </p>
            <UProgress
              :model-value="percentage(host.data.disk_used, host.data.disk_total)"
              size="xs"
              class="mt-2"
            />
          </UCard>
        </template>

        <template v-else-if="!host">
          <USkeleton
            v-for="index in 4"
            :key="index"
            class="h-24 rounded-lg"
          />
        </template>
      </div>

      <HostError
        v-if="applications?.error"
        :error="applications.error"
      />

      <UAlert
        v-for="failure in failures"
        :key="failure.name"
        color="error"
        variant="subtle"
        icon="i-lucide-circle-x"
        :title="`${failure.unit} failed to start`"
        :description="failure.error ?? `The unit exited with ${failure.result ?? 'an error'}.`"
        :actions="
          missingSecretIn(failure.error)
            ? [
                {
                  label: 'Create secret',
                  color: 'error',
                  variant: 'outline',
                  to: secretsIndex.url(environment.id, {
                    query: { secret: missingSecretIn(failure.error), application: failure.name },
                  }),
                },
              ]
            : []
        "
      />

      <div class="flex flex-wrap items-center justify-between gap-3">
        <UTabs
          v-model="filter"
          :items="tabs"
          :content="false"
          size="sm"
          color="neutral"
        />

        <p
          v-if="host?.data?.auto_update_next_run"
          class="flex items-center gap-1.5 text-sm text-muted"
        >
          <UIcon
            name="i-lucide-refresh-cw"
            class="size-4"
          />
          Auto-update timer: next run {{ formatRelative(host.data.auto_update_next_run) }}
        </p>
      </div>

      <ul
        class="flex flex-col divide-y divide-default rounded-lg border border-default md:hidden"
        aria-label="Applications"
      >
        <li
          v-for="application in visible"
          :key="application.name"
        >
          <Link
            :href="show.url([environment.id, application.name])"
            class="flex min-h-16 items-center gap-3 px-3 py-2.5"
          >
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-elevated text-primary">
              <UIcon
                :name="application.pods ? 'i-lucide-layers' : 'i-lucide-box'"
                class="size-4"
              />
            </span>
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="truncate font-medium text-highlighted">{{ application.name }}</span>
              <span class="truncate text-xs text-muted">{{ describeApplication(application) }}</span>
            </span>
            <UnitStatusBadge :status="application.status" />
          </Link>
        </li>

        <li
          v-if="!applications"
          class="flex flex-col gap-3 p-3"
        >
          <USkeleton
            v-for="index in 4"
            :key="index"
            class="h-10"
          />
        </li>

        <li
          v-else-if="!visible.length"
          class="p-4 text-center text-sm text-muted"
        >
          No applications match this filter.
        </li>
      </ul>

      <UTable
        :data="visible"
        :columns="columns"
        :loading="!applications"
        empty="No applications match this filter."
        class="hidden rounded-lg border border-default md:block"
      >
        <template #name-cell="{ row }">
          <Link
            :href="show.url([environment.id, row.original.name])"
            class="flex flex-col"
          >
            <span class="font-medium text-highlighted">{{ row.original.name }}</span>
            <span class="font-mono text-xs text-muted">{{ quadletName(row.original) }}</span>
          </Link>
        </template>

        <template #status-cell="{ row }">
          <UnitStatusBadge :status="row.original.status" />
        </template>

        <template #units-cell="{ row }">
          <span class="text-muted">{{ describeUnits(row.original) }}</span>
        </template>

        <template #image-cell="{ row }">
          <span class="font-mono text-xs">
            {{ row.original.image?.split('/').pop() ?? '–' }}
            <UIcon
              v-if="withUpdates.has(row.original.name)"
              name="i-lucide-circle-arrow-up"
              class="size-3.5 align-text-bottom text-primary"
              aria-label="Image update available"
            />
          </span>
        </template>

        <template #ports-cell="{ row }">
          <span class="text-muted">{{ row.original.ports.map(formatPort).join(', ') || 'host network' }}</span>
        </template>

        <template #memory-cell="{ row }">
          <span class="text-muted">{{ formatBytes(row.original.memory) }}</span>
        </template>

        <template #actions-cell="{ row }">
          <div class="flex justify-end">
            <UDropdownMenu
              :items="actions(row.original)"
              :content="{ align: 'end' }"
            >
              <UButton
                icon="i-lucide-ellipsis-vertical"
                color="neutral"
                variant="ghost"
                :aria-label="`Actions for ${row.original.name}`"
              />
            </UDropdownMenu>
          </div>
        </template>
      </UTable>

      <ApplicationForceStopModal
        v-if="forceStopping"
        v-model:open="isForceStopping"
        :environment="environment"
        :application="forceStopping"
      />

      <ApplicationRemoveModal
        v-if="removing"
        v-model:open="isRemoving"
        :environment="environment"
        :application="removing"
      />
    </template>
  </UDashboardPanel>
</template>
