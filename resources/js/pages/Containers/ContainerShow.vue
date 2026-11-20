<script setup lang="ts">
import { show as applicationShow } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { index as containersIndex } from '@/actions/Modules/Web/Containers/Controllers/ContainerController'
import ContainerOperationController from '@/actions/Modules/Web/Containers/Controllers/ContainerOperationController'
import journal from '@/actions/Modules/Web/Journal/Controllers/JournalController'
import JournalLog from '@/components/Applications/JournalLog.vue'
import HostError from '@/components/Environments/HostError.vue'
import QuadletEditModal from '@/components/Quadlets/QuadletEditModal.vue'
import CodeBlock from '@/components/Ui/CodeBlock.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import StatCard from '@/components/Ui/StatCard.vue'
import StatGrid from '@/components/Ui/StatGrid.vue'
import StateBadge from '@/components/Ui/StateBadge.vue'
import { useHostPolling } from '@/composables/applications'
import { useHostAction } from '@/composables/hostActions'
import { useHostReload } from '@/composables/polling'
import type {
  ContainerDetailData,
  ContainerOperation,
  ContainerStatsData,
  EnvironmentData,
  HostProperty,
  JournalEntryData,
  QuadletFileData,
} from '@/types'
import { containerState, ratePerSecond, type ContainerCounter } from '@/utils/containers'
import { formatBytes, formatPercent, formatPort, formatRate, formatRelative, formatUptime } from '@/utils/format'
import { Head, Link } from '@inertiajs/vue3'
import type { BreadcrumbItem, TableColumn, TabsItem } from '@nuxt/ui'
import { computed, ref, watch } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  name: string
  container?: HostProperty<ContainerDetailData>
  logs?: HostProperty<JournalEntryData[]>
  quadlet?: HostProperty<QuadletFileData | null>
  stats?: HostProperty<ContainerStatsData>
}>()

const detail = computed(() => props.container?.data ?? null)
const summary = computed(() => detail.value?.container ?? null)
const state = computed(() => (summary.value ? containerState(summary.value) : null))
const isRunning = computed(() => summary.value?.state === 'running')

useHostPolling(['container', 'logs'], () => !!state.value?.busy)

const tab = ref('overview')

const tabs = computed<TabsItem[]>(() => [
  { label: 'Overview', value: 'overview', icon: 'i-lucide-layout-dashboard' },
  { label: 'Logs', value: 'logs', icon: 'i-lucide-scroll-text' },
  ...(isRunning.value ? [{ label: 'Stats', value: 'stats', icon: 'i-lucide-activity' }] : []),
  ...(summary.value?.quadlet ? [{ label: 'Quadlet', value: 'quadlet', icon: 'i-lucide-file-code' }] : []),
])

const showsErrorsOnly = ref(false)
const isFollowing = ref(false)

const errorCount = computed(() => (props.logs?.data ?? []).filter((entry) => entry.priority <= 3).length)

const visibleLogs = computed(() =>
  (props.logs?.data ?? []).filter((entry) => !showsErrorsOnly.value || entry.priority <= 3),
)

const { pause: stopFollowing, resume: startFollowing } = useHostReload(['logs'], 5000, {
  immediate: false,
})

const toggleFollowing = (): void => {
  isFollowing.value = !isFollowing.value

  if (isFollowing.value) {
    startFollowing()

    return
  }

  stopFollowing()
}

const isEditingQuadlet = ref(false)

/**
 * The sample before the current one, to turn the I/O totals into rates.
 */
const previousStats = ref<ContainerStatsData | null>(null)

watch(
  () => props.stats?.data,
  (_current, previous) => {
    previousStats.value = previous ?? null
  },
)

const { pause: stopSampling, resume: startSampling } = useHostReload(['stats'], 5000, {
  immediate: false,
  immediateCallback: true,
})

watch(
  tab,
  (current) => {
    if (current === 'stats') {
      startSampling()

      return
    }

    stopSampling()
  },
  { immediate: true },
)

const rate = (counter: ContainerCounter): number | null =>
  ratePerSecond(props.stats?.data, previousStats.value, counter)

const breadcrumb = computed<BreadcrumbItem[]>(() => [
  { label: 'Containers', to: containersIndex.url(props.environment.id) },
  ...(summary.value?.application
    ? [{ label: summary.value.application, to: applicationShow.url([props.environment.id, summary.value.application]) }]
    : []),
  { label: props.name },
])

type Binding = { type: string; host: string; container: string; mode: string }

const bindings = computed<Binding[]>(() => {
  if (!detail.value) {
    return []
  }

  return [
    ...detail.value.container.ports.map((port) => {
      const [host, target] = port.split('->')

      return { type: 'port', host: host ?? port, container: target ?? port, mode: '–' }
    }),
    ...detail.value.mounts.map((mount) => ({
      type: mount.type,
      host: mount.source,
      container: mount.destination,
      mode: mount.mode ?? '–',
    })),
    ...detail.value.secrets.map((secret) => ({
      type: 'secret',
      host: secret.name,
      container: secret.target,
      mode: secret.type,
    })),
    ...detail.value.networks.map((network) => ({
      type: 'network',
      host: network.name,
      container: network.ip ?? '–',
      mode: '–',
    })),
  ]
})

const bindingColumns: TableColumn<Binding>[] = [
  { accessorKey: 'type', header: 'Type' },
  { accessorKey: 'host', header: 'Host' },
  { accessorKey: 'container', header: 'Container' },
  { accessorKey: 'mode', header: 'Mode' },
]

const { send, processing } = useHostAction()

const operate = (operation: ContainerOperation): void =>
  send('post', ContainerOperationController.url([props.environment.id, props.name, operation]))

const memoryHint = computed(() =>
  detail.value?.memory_limit ? `of ${formatBytes(detail.value.memory_limit)} limit` : 'no limit',
)
</script>

<template>
  <Head :title="name" />

  <UDashboardPanel id="container">
    <template #header>
      <AppNavbar
        :breadcrumb="breadcrumb"
        :back="{ label: 'Containers', to: containersIndex.url(environment.id) }"
      />
    </template>

    <template #body>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-1">
          <div class="flex items-center gap-3">
            <h1 class="font-mono text-2xl font-semibold text-highlighted">{{ name }}</h1>
            <StateBadge
              v-if="state"
              v-bind="state"
            />
          </div>

          <p
            v-if="summary"
            class="font-mono text-sm break-all text-muted"
          >
            {{ summary.image }}
            <template v-if="summary.application">
              · part of
              <Link
                :href="applicationShow.url([environment.id, summary.application])"
                class="text-primary"
                >{{ summary.application }}</Link
              >
            </template>
            <template v-if="isRunning"> · up {{ formatUptime(summary.started_at) }}</template>
          </p>
          <USkeleton
            v-else-if="!container"
            class="h-5 w-72"
          />
        </div>

        <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
          <UButton
            v-if="isRunning"
            label="Stop"
            icon="i-lucide-square"
            color="neutral"
            variant="outline"
            class="justify-center"
            :loading="processing"
            @click="operate('stop')"
          />
          <UButton
            v-else
            label="Start"
            icon="i-lucide-play"
            color="neutral"
            variant="outline"
            class="justify-center"
            :disabled="!summary"
            :loading="processing"
            @click="operate('start')"
          />
          <UButton
            label="Restart"
            icon="i-lucide-rotate-cw"
            class="justify-center"
            :disabled="!isRunning"
            @click="operate('restart')"
          />
        </div>
      </div>

      <HostError
        v-if="container?.error"
        :error="container.error"
      />

      <UAlert
        v-if="summary && !summary.quadlet"
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Not managed by Quadlet"
        description="This container was started with podman run. It has no systemd unit, so it won't restart after a reboot."
      />

      <UTabs
        v-model="tab"
        :items="tabs"
        :content="false"
        variant="link"
        color="neutral"
        :ui="{ list: 'overflow-x-auto', trigger: 'shrink-0' }"
      />

      <template v-if="tab === 'overview'">
        <StatGrid :loading="!container">
          <template v-if="summary && detail">
            <StatCard
              label="CPU"
              :value="formatPercent(summary.cpu)"
            />
            <StatCard
              label="Memory"
              :value="formatBytes(summary.memory)"
              :hint="memoryHint"
              :progress="
                detail.memory_limit && summary.memory
                  ? Math.round((summary.memory / detail.memory_limit) * 100)
                  : undefined
              "
            />
            <StatCard
              label="Ports"
              :value="summary.ports.length ? summary.ports.map(formatPort).join(', ') : '–'"
            />
            <StatCard
              label="Health"
              :value="summary.health ? summary.health[0]?.toUpperCase() + summary.health.slice(1) : 'No check'"
              :hint="
                detail.health_check?.last_check
                  ? `checked ${formatRelative(detail.health_check.last_check)}`
                  : undefined
              "
              :color="summary.health === 'healthy' ? 'success' : summary.health === 'unhealthy' ? 'error' : undefined"
            />
          </template>
        </StatGrid>

        <div class="grid gap-4 lg:grid-cols-3">
          <div class="flex min-w-0 flex-col gap-4 lg:col-span-2">
            <UCard :ui="{ body: 'p-0 sm:p-0' }">
              <template #header>
                <div class="flex items-center justify-between">
                  <h2 class="font-semibold text-highlighted">Logs</h2>
                  <UButton
                    label="Open journal"
                    color="neutral"
                    variant="link"
                    size="sm"
                    :to="journal.url(environment.id)"
                  />
                </div>
              </template>

              <HostError
                v-if="logs?.error"
                :error="logs.error"
                class="m-4"
              />
              <JournalLog
                v-else-if="logs?.data"
                :entries="logs.data.slice(-8)"
              />
              <USkeleton
                v-else
                class="m-4 h-32"
              />
            </UCard>

            <UCard :ui="{ body: 'p-0 sm:p-0' }">
              <template #header>
                <h2 class="font-semibold text-highlighted">Ports and mounts</h2>
              </template>

              <UTable
                :data="bindings"
                :columns="bindingColumns"
                :loading="!container"
                empty="This container publishes no ports and mounts nothing."
              >
                <template #host-cell="{ row }">
                  <span class="font-mono text-xs break-all">{{ row.original.host }}</span>
                </template>
                <template #container-cell="{ row }">
                  <span class="font-mono text-xs break-all">{{ row.original.container }}</span>
                </template>
                <template #mode-cell="{ row }">
                  <span class="text-muted">{{ row.original.mode }}</span>
                </template>
              </UTable>
            </UCard>
          </div>

          <div class="flex flex-col gap-4">
            <UCard>
              <template #header>
                <h2 class="font-semibold text-highlighted">Details</h2>
              </template>

              <dl
                v-if="detail && summary"
                class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm"
              >
                <dt class="text-muted">Container ID</dt>
                <dd class="font-mono text-xs">{{ summary.id }}</dd>

                <template v-if="summary.unit">
                  <dt class="text-muted">Unit</dt>
                  <dd class="font-mono text-xs break-all">{{ summary.unit }}</dd>
                </template>

                <template v-if="summary.quadlet">
                  <dt class="text-muted">Quadlet</dt>
                  <dd class="font-mono text-xs break-all">{{ summary.quadlet }}</dd>
                </template>

                <template v-if="summary.pod">
                  <dt class="text-muted">Pod</dt>
                  <dd class="font-mono text-xs">{{ summary.pod }}</dd>
                </template>

                <dt class="text-muted">Created</dt>
                <dd>{{ formatRelative(detail.created_at) }}</dd>

                <dt class="text-muted">Restart policy</dt>
                <dd>{{ detail.restart_policy ?? 'no' }} · {{ detail.restarts }} restarts</dd>

                <template v-if="detail.user">
                  <dt class="text-muted">User</dt>
                  <dd class="font-mono text-xs">{{ detail.user }}</dd>
                </template>

                <dt class="text-muted">Auto-update</dt>
                <dd>{{ detail.auto_update ?? 'Off' }}</dd>

                <template v-if="detail.digest">
                  <dt class="text-muted">Image digest</dt>
                  <dd class="truncate font-mono text-xs">{{ detail.digest }}</dd>
                </template>
              </dl>
              <USkeleton
                v-else-if="!container"
                class="h-40"
              />
            </UCard>

            <UCard>
              <template #header>
                <h2 class="font-semibold text-highlighted">Environment</h2>
              </template>

              <dl
                v-if="detail && (detail.environment.length || detail.secrets.length)"
                class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 font-mono text-xs"
              >
                <template
                  v-for="variable in detail.environment"
                  :key="variable.name"
                >
                  <dt class="text-muted">{{ variable.name }}</dt>
                  <dd class="break-all">{{ variable.value }}</dd>
                </template>
                <template
                  v-for="secret in detail.secrets"
                  :key="secret.name"
                >
                  <dt class="text-muted">{{ secret.target }}</dt>
                  <dd class="flex items-center gap-1 text-muted">
                    <UIcon
                      name="i-lucide-key-round"
                      class="size-3"
                    />
                    from {{ secret.name }}
                  </dd>
                </template>
              </dl>
              <p
                v-else-if="detail"
                class="text-sm text-muted"
              >
                No variables are set.
              </p>
            </UCard>
          </div>
        </div>
      </template>

      <UCard
        v-else-if="tab === 'logs'"
        :ui="{ body: 'p-0 sm:p-0' }"
      >
        <template #header>
          <div class="flex flex-wrap items-center gap-2">
            <UButton
              :label="isFollowing ? 'Following' : 'Follow'"
              :icon="isFollowing ? 'i-lucide-pause' : 'i-lucide-play'"
              color="neutral"
              :variant="isFollowing ? 'soft' : 'outline'"
              size="sm"
              :aria-pressed="isFollowing"
              @click="toggleFollowing"
            />
            <UButton
              :label="errorCount ? `Errors · ${errorCount}` : 'Errors'"
              icon="i-lucide-circle-alert"
              :color="showsErrorsOnly ? 'error' : 'neutral'"
              :variant="showsErrorsOnly ? 'soft' : 'outline'"
              size="sm"
              :aria-pressed="showsErrorsOnly"
              @click="showsErrorsOnly = !showsErrorsOnly"
            />
            <UButton
              label="Open journal"
              color="neutral"
              variant="link"
              size="sm"
              class="ms-auto"
              :to="journal.url(environment.id)"
            />
          </div>
        </template>

        <HostError
          v-if="logs?.error"
          :error="logs.error"
          class="m-4"
        />
        <template v-else-if="logs?.data">
          <JournalLog
            v-if="visibleLogs.length"
            :entries="visibleLogs"
          />
          <p
            v-else
            class="p-4 text-sm text-muted"
          >
            {{ showsErrorsOnly ? 'No errors in the recent logs.' : 'This container has not logged anything yet.' }}
          </p>
        </template>
        <USkeleton
          v-else
          class="m-4 h-64"
        />
      </UCard>

      <div
        v-else-if="tab === 'stats'"
        class="flex flex-col gap-4"
      >
        <HostError
          v-if="stats?.error"
          :error="stats.error"
        />

        <StatGrid
          v-else
          :loading="!stats?.data"
        >
          <template v-if="stats?.data">
            <StatCard
              label="CPU"
              :value="formatPercent(stats.data.cpu)"
            />
            <StatCard
              label="Memory"
              :value="formatBytes(stats.data.memory)"
              :hint="stats.data.memory_limit ? `of ${formatBytes(stats.data.memory_limit)}` : 'no limit'"
              :progress="
                stats.data.memory_limit && stats.data.memory
                  ? Math.round((stats.data.memory / stats.data.memory_limit) * 100)
                  : undefined
              "
            />
            <StatCard
              label="Network"
              :value="formatRate(rate('net_in'))"
              :hint="`in · out ${formatRate(rate('net_out'))}`"
            />
            <StatCard
              label="Disk"
              :value="formatRate(rate('block_out'))"
              :hint="`written · read ${formatRate(rate('block_in'))}`"
            />
          </template>
        </StatGrid>

        <UCard v-if="stats?.data">
          <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_1fr_auto_1fr]">
            <dt class="text-muted">Received</dt>
            <dd>{{ formatBytes(stats.data.net_in) }}</dd>
            <dt class="text-muted">Sent</dt>
            <dd>{{ formatBytes(stats.data.net_out) }}</dd>
            <dt class="text-muted">Read</dt>
            <dd>{{ formatBytes(stats.data.block_in) }}</dd>
            <dt class="text-muted">Written</dt>
            <dd>{{ formatBytes(stats.data.block_out) }}</dd>
            <dt class="text-muted">Processes</dt>
            <dd>{{ stats.data.pids ?? '–' }}</dd>
            <dt class="text-muted">Sampled</dt>
            <dd>{{ formatRelative(stats.data.sampled_at) }}</dd>
          </dl>
          <p class="mt-3 text-xs text-muted">
            Totals since the container started. Rates appear after the second sample, 5 seconds apart.
          </p>
        </UCard>
      </div>

      <div
        v-else
        class="flex flex-col gap-4"
      >
        <HostError
          v-if="quadlet?.error"
          :error="quadlet.error"
        />

        <CodeBlock
          v-if="quadlet?.data"
          :title="quadlet.data.name"
          :subtitle="quadlet.data.path"
          :content="quadlet.data.content"
          numbered
        >
          <template
            v-if="summary?.application"
            #actions
          >
            <UButton
              label="Edit"
              icon="i-lucide-pencil"
              color="neutral"
              variant="outline"
              size="sm"
              @click="isEditingQuadlet = true"
            />
          </template>
        </CodeBlock>
        <p
          v-else-if="quadlet && !quadlet.error"
          class="text-sm text-muted"
        >
          Pods couldn't find {{ summary?.quadlet }} among the files of {{ summary?.application }}.
        </p>
        <USkeleton
          v-else-if="!quadlet"
          class="h-64"
        />

        <QuadletEditModal
          v-if="summary?.application"
          v-model:open="isEditingQuadlet"
          :environment="environment"
          :application="summary.application"
          :file="quadlet?.data ?? null"
        />
      </div>
    </template>
  </UDashboardPanel>
</template>
