<script setup lang="ts">
import { index as applicationsIndex } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { show as containerShow } from '@/actions/Modules/Web/Containers/Controllers/ContainerController'
import { destroy as disableOnDemand } from '@/actions/Modules/Web/Applications/Controllers/ApplicationOnDemandController'
import ApplicationSourceCard from '@/components/Applications/ApplicationSourceCard.vue'
import ApplicationForceStopModal from '@/components/Applications/ApplicationForceStopModal.vue'
import ApplicationOnDemandModal from '@/components/Applications/ApplicationOnDemandModal.vue'
import ApplicationRemoveModal from '@/components/Applications/ApplicationRemoveModal.vue'
import { index as secretsIndex } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import JournalLog from '@/components/Applications/JournalLog.vue'
import UnitStatusBadge from '@/components/Applications/UnitStatusBadge.vue'
import HostError from '@/components/Environments/HostError.vue'
import QuadletEditModal from '@/components/Quadlets/QuadletEditModal.vue'
import SecretCreateModal from '@/components/Secrets/SecretCreateModal.vue'
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import StateBadge from '@/components/Ui/StateBadge.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import { useApplicationOperations, useHostPolling } from '@/composables/applications'
import type {
  ApplicationDetailData,
  ContainerData,
  EnvironmentData,
  HostProperty,
  InstallationData,
  JournalEntryData,
  OnDemandData,
  OnDemandSuggestionData,
  QuadletFileData,
  SecretData,
} from '@/types'
import { containerState, shortImage } from '@/utils/containers'
import { formatBytes, formatPercent, formatPort, formatRelative } from '@/utils/format'
import { isTransitioning } from '@/utils/status'
import { Head, Link, router } from '@inertiajs/vue3'
import type { BreadcrumbItem, TableColumn, TabsItem } from '@nuxt/ui'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  name: string
  application?: HostProperty<ApplicationDetailData>
  ondemand?: HostProperty<OnDemandData>
  ondemandSuggestion?: HostProperty<OnDemandSuggestionData>
  files?: HostProperty<QuadletFileData[]>
  installation?: HostProperty<InstallationData | null>
  logs?: HostProperty<JournalEntryData[]>
  secrets?: HostProperty<SecretData[]>
}>()

const detail = computed(() => props.application?.data ?? null)
const summary = computed(() => detail.value?.application ?? null)
const manifest = computed(() => props.installation?.data?.manifest ?? null)

const tab = ref('overview')

const tabs: TabsItem[] = [
  { label: 'Overview', value: 'overview', icon: 'i-lucide-layout-dashboard' },
  { label: 'Containers', value: 'containers', icon: 'i-lucide-box' },
  { label: 'Quadlet files', value: 'files', icon: 'i-lucide-file-code' },
  { label: 'Logs', value: 'logs', icon: 'i-lucide-scroll-text' },
  { label: 'Secrets', value: 'secrets', icon: 'i-lucide-key-round' },
]

const containerColumns: TableColumn<ContainerData>[] = [
  { accessorKey: 'name', header: 'Container' },
  { accessorKey: 'state', header: 'Status' },
  { accessorKey: 'image', header: 'Image' },
  { accessorKey: 'cpu', header: 'CPU', meta: { class: { th: 'text-right', td: 'text-right' } } },
  { accessorKey: 'memory', header: 'Memory', meta: { class: { th: 'text-right', td: 'text-right' } } },
]

type ApplicationSecret = { name: string; driver: string | null; updated_at: string | null; missing: boolean }

/**
 * The secrets the application references, with what the host knows about each one.
 */
const applicationSecrets = computed<ApplicationSecret[]>(() =>
  (detail.value?.secrets ?? []).map((secretName) => {
    const hostSecret = props.secrets?.data?.find((secret) => secret.name === secretName)

    return {
      name: secretName,
      driver: hostSecret?.driver ?? null,
      updated_at: hostSecret?.updated_at ?? null,
      missing: props.secrets?.data ? !hostSecret || hostSecret.missing : false,
    }
  }),
)

const secretColumns: TableColumn<ApplicationSecret>[] = [
  { accessorKey: 'name', header: 'Name' },
  { accessorKey: 'driver', header: 'Driver' },
  { accessorKey: 'updated_at', header: 'Updated' },
  { id: 'actions', meta: { class: { td: 'text-right' } } },
]

const missingSecretCount = computed(() => applicationSecrets.value.filter((secret) => secret.missing).length)

const creatingSecret = ref<string | null>(null)

const isCreatingSecret = computed({
  get: () => creatingSecret.value !== null,
  set: (isOpen: boolean) => {
    if (!isOpen) {
      creatingSecret.value = null
    }
  },
})

const editingFile = ref<QuadletFileData | null>(null)

const isEditingFile = computed({
  get: () => editingFile.value !== null,
  set: (isOpen: boolean) => {
    if (!isOpen) {
      editingFile.value = null
    }
  },
})

const breadcrumb = computed<BreadcrumbItem[]>(() => [
  { label: 'Applications', to: applicationsIndex.url(props.environment.id) },
  { label: props.name },
])

const subtitle = computed(() =>
  [
    summary.value?.description ?? manifest.value?.description,
    summary.value?.status === 'running' && summary.value.since
      ? `up ${formatRelative(summary.value.since).replace(/ ago$/, '')}`
      : null,
    summary.value?.url?.replace(/^https?:\/\//, ''),
  ]
    .filter(Boolean)
    .join(' · '),
)

useHostPolling(['application', 'ondemand', 'logs'], () => !!summary.value && isTransitioning(summary.value.status))

const onDemand = computed(() => props.ondemand?.data ?? null)

/**
 * An on-demand application that isn't running sleeps until the next request reaches its socket.
 */
const isAsleep = computed(
  () => !!onDemand.value?.enabled && !onDemand.value.awake && summary.value?.status !== 'running',
)

const isConfiguringOnDemand = ref(false)

const isDisablingOnDemand = ref(false)

const isTurningOffOnDemand = ref(false)

const onToggleOnDemand = (enabled: boolean): void => {
  if (enabled) {
    isConfiguringOnDemand.value = true
  } else {
    isDisablingOnDemand.value = true
  }
}

const onDisableOnDemand = (close: () => void): void => {
  router.delete(disableOnDemand.url([props.environment.id, props.name]), {
    preserveScroll: true,
    onStart: () => (isTurningOffOnDemand.value = true),
    onFinish: () => (isTurningOffOnDemand.value = false),
    onSuccess: () => close(),
  })
}

const isRunning = computed(() => summary.value?.status === 'running')

/**
 * An application in a restart loop shows as activating, so it can be stopped too.
 */
const canStop = computed(() => ['running', 'activating', 'reloading'].includes(summary.value?.status ?? ''))

const canForceStop = computed(() => !!summary.value && !['inactive', 'unknown'].includes(summary.value.status))

const isForceStopping = ref(false)

const { run } = useApplicationOperations(() => props.environment)

const isRemoving = ref(false)
</script>

<template>
  <Head :title="name" />

  <UDashboardPanel id="application">
    <template #header>
      <AppNavbar
        :breadcrumb="breadcrumb"
        :back="{ label: 'Applications', to: applicationsIndex.url(environment.id) }"
      />
    </template>

    <template #body>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-1">
          <div class="flex items-center gap-3">
            <UIcon
              v-if="manifest?.icon"
              :name="manifest.icon"
              class="size-7 shrink-0 text-primary"
            />
            <h1 class="text-2xl font-semibold text-highlighted">{{ manifest?.label ?? name }}</h1>
            <span
              v-if="manifest?.label && manifest.label !== name"
              class="font-mono text-sm text-muted"
              >{{ name }}</span
            >
            <StateBadge
              v-if="isAsleep"
              label="Asleep"
              color="info"
            />
            <UnitStatusBadge
              v-else-if="summary"
              :status="summary.status"
            />
          </div>

          <p
            v-if="subtitle"
            class="text-muted"
          >
            {{ subtitle }}
          </p>
          <USkeleton
            v-else-if="!application"
            class="h-5 w-72"
          />
        </div>

        <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap">
          <UButton
            v-if="canStop"
            label="Stop"
            class="justify-center"
            icon="i-lucide-square"
            color="neutral"
            variant="outline"
            @click="run(name, 'down')"
          />
          <UButton
            v-else
            label="Start"
            class="justify-center"
            icon="i-lucide-play"
            color="neutral"
            variant="outline"
            :disabled="!summary || isAsleep"
            :title="isAsleep ? 'It starts on the next request to its socket.' : undefined"
            @click="run(name, 'up')"
          />
          <UButton
            v-if="canForceStop"
            label="Force stop"
            class="justify-center"
            icon="i-lucide-octagon-x"
            color="error"
            variant="outline"
            @click="isForceStopping = true"
          />
          <UButton
            label="Restart"
            class="justify-center"
            icon="i-lucide-rotate-cw"
            color="neutral"
            variant="outline"
            :disabled="!isRunning"
            @click="run(name, 'restart')"
          />
          <UButton
            label="Pull and redeploy"
            icon="i-lucide-download"
            class="col-span-2 justify-center sm:col-span-1"
            :disabled="!summary || isTransitioning(summary.status)"
            @click="run(name, 'redeploy')"
          />
          <UButton
            label="Remove"
            icon="i-lucide-trash-2"
            color="error"
            variant="ghost"
            class="col-span-2 justify-center sm:col-span-1"
            @click="isRemoving = true"
          />
        </div>
      </div>

      <HostError
        v-if="application?.error"
        :error="application.error"
      />

      <UTabs
        v-model="tab"
        :items="tabs"
        :content="false"
        variant="link"
        color="neutral"
        :ui="{ list: 'overflow-x-auto', trigger: 'shrink-0' }"
      />

      <div
        v-if="tab === 'overview'"
        class="grid gap-4 lg:grid-cols-3"
      >
        <div class="flex flex-col gap-4 lg:col-span-2">
          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <div class="flex items-center justify-between">
                <h2 class="font-semibold text-highlighted">Containers</h2>
                <span
                  v-if="summary?.kind === 'pod'"
                  class="text-sm text-muted"
                  >in pod {{ name }}</span
                >
              </div>
            </template>

            <ul
              v-if="detail"
              class="divide-y divide-default"
            >
              <li
                v-for="container in detail.items"
                :key="container.name"
                class="flex items-center gap-3 px-4 py-3"
              >
                <span
                  class="size-2 shrink-0 rounded-full"
                  :class="container.state === 'running' ? 'bg-success' : 'bg-muted'"
                  aria-hidden="true"
                />
                <div class="flex min-w-0 flex-1 flex-col">
                  <Link
                    :href="containerShow.url([environment.id, container.name])"
                    class="font-medium text-highlighted hover:text-primary"
                    >{{ container.name }}</Link
                  >
                  <span class="truncate font-mono text-xs text-muted">{{ container.image }}</span>
                </div>
                <span class="w-14 text-right text-sm text-muted">{{ formatPercent(container.cpu) }}</span>
                <span class="w-20 text-right text-sm text-muted">{{ formatBytes(container.memory) }}</span>
              </li>
            </ul>
            <div
              v-else-if="!application"
              class="flex flex-col gap-3 p-4"
            >
              <USkeleton
                v-for="index in 3"
                :key="index"
                class="h-10"
              />
            </div>
          </UCard>

          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <div class="flex items-center justify-between">
                <h2 class="font-semibold text-highlighted">Journal</h2>
                <UButton
                  label="All logs"
                  color="neutral"
                  variant="link"
                  size="sm"
                  @click="tab = 'logs'"
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
        </div>

        <div class="flex flex-col gap-4">
          <ApplicationSourceCard
            :environment="environment"
            :application="name"
            :installation="installation"
          />

          <UCard>
            <template #header>
              <h2 class="font-semibold text-highlighted">Details</h2>
            </template>

            <dl
              v-if="detail && summary"
              class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm"
            >
              <dt class="text-muted">Systemd unit</dt>
              <dd class="font-mono text-xs break-all">{{ summary.unit }}</dd>

              <template v-if="detail.path">
                <dt class="text-muted">Installed from</dt>
                <dd class="font-mono text-xs break-all">{{ detail.path }}</dd>
              </template>

              <dt class="text-muted">Auto-update</dt>
              <dd>{{ summary.auto_update ?? 'Off' }}</dd>

              <dt class="text-muted">Ports</dt>
              <dd>{{ summary.ports.map(formatPort).join(', ') || 'host network' }}</dd>

              <template v-if="detail.volumes.length">
                <dt class="text-muted">Volumes</dt>
                <dd class="flex flex-col">
                  <span
                    v-for="volume in detail.volumes"
                    :key="volume.name"
                    >{{ volume.name }} · {{ formatBytes(volume.size) }}</span
                  >
                </dd>
              </template>
            </dl>
            <USkeleton
              v-else-if="!application"
              class="h-32"
            />
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-center justify-between gap-2">
                <h2 class="font-semibold text-highlighted">On demand</h2>
                <USwitch
                  v-if="onDemand"
                  :model-value="onDemand.enabled"
                  aria-label="Run on demand"
                  :disabled="!ondemandSuggestion"
                  @update:model-value="onToggleOnDemand"
                />
              </div>
            </template>

            <HostError
              v-if="ondemand?.error"
              :error="ondemand.error"
            />
            <div
              v-else-if="onDemand?.enabled && onDemand.settings"
              class="flex flex-col gap-3 text-sm"
            >
              <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
                <dt class="text-muted">Socket</dt>
                <dd class="font-mono text-xs">
                  {{ onDemand.settings.listen ?? '*' }}:{{ onDemand.settings.port }}
                  <span
                    v-if="!onDemand.listening"
                    class="text-error"
                    >(not listening)</span
                  >
                </dd>

                <dt class="text-muted">Idle timeout</dt>
                <dd class="font-mono text-xs">{{ onDemand.settings.idle_timeout }}</dd>

                <template v-if="onDemand.settings.workers.length">
                  <dt class="text-muted">Workers</dt>
                  <dd class="font-mono text-xs break-all">{{ onDemand.settings.workers.join(', ') }}</dd>
                </template>

                <template v-if="onDemand.settings.schedule">
                  <dt class="text-muted">Scheduler</dt>
                  <dd class="font-mono text-xs break-all">
                    {{ onDemand.settings.schedule.container }} at {{ onDemand.settings.schedule.at.join(', ') }} UTC
                  </dd>
                </template>
              </dl>

              <UButton
                label="Edit"
                icon="i-lucide-pencil"
                color="neutral"
                variant="outline"
                size="sm"
                class="self-start"
                :disabled="!ondemandSuggestion"
                @click="isConfiguringOnDemand = true"
              />
            </div>
            <p
              v-else-if="onDemand"
              class="text-sm text-muted"
            >
              Runs all the time. Turn this on to start it on its first request and let it sleep when idle.
            </p>
            <USkeleton
              v-else
              class="h-12"
            />
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-center justify-between">
                <h2 class="font-semibold text-highlighted">Secrets</h2>
                <UButton
                  label="Manage"
                  color="neutral"
                  variant="link"
                  size="sm"
                  :to="secretsIndex.url(environment.id)"
                />
              </div>
            </template>

            <ul
              v-if="detail?.secrets.length"
              class="flex flex-col gap-2"
            >
              <li
                v-for="secret in detail.secrets"
                :key="secret"
                class="flex items-center gap-2 font-mono text-sm"
              >
                <UIcon
                  name="i-lucide-key-round"
                  class="size-4 text-muted"
                />
                {{ secret }}
              </li>
            </ul>
            <p
              v-else-if="detail"
              class="text-sm text-muted"
            >
              This application uses no secrets.
            </p>
          </UCard>
        </div>
      </div>

      <UCard
        v-else-if="tab === 'containers'"
        :ui="{ body: 'p-0 sm:p-0' }"
      >
        <UTable
          :data="detail?.items ?? []"
          :columns="containerColumns"
          :loading="!application"
          empty="This application has no containers."
        >
          <template #name-cell="{ row }">
            <Link
              :href="containerShow.url([environment.id, row.original.name])"
              class="font-medium text-highlighted hover:text-primary"
              >{{ row.original.name }}</Link
            >
          </template>
          <template #state-cell="{ row }">
            <StateBadge v-bind="containerState({ state: row.original.state, exit_code: null })" />
          </template>
          <template #image-cell="{ row }">
            <span
              class="font-mono text-xs text-muted"
              :title="row.original.image ?? undefined"
              >{{ shortImage(row.original.image) }}</span
            >
          </template>
          <template #cpu-cell="{ row }">
            {{ formatPercent(row.original.cpu) }}
          </template>
          <template #memory-cell="{ row }">
            {{ formatBytes(row.original.memory) }}
          </template>
        </UTable>
      </UCard>

      <div
        v-else-if="tab === 'secrets'"
        class="flex flex-col gap-4"
      >
        <HostError
          v-if="secrets?.error"
          :error="secrets.error"
        />

        <UAlert
          v-if="missingSecretCount"
          color="error"
          variant="subtle"
          icon="i-lucide-key-round"
          :title="missingSecretCount === 1 ? '1 secret is missing' : `${missingSecretCount} secrets are missing`"
          description="The application references them with Secret=, but they don't exist on the host, so its units can't start."
        />

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <div class="flex items-center justify-between">
              <h2 class="font-semibold text-highlighted">Secrets</h2>
              <UButton
                label="Manage"
                color="neutral"
                variant="link"
                size="sm"
                :to="secretsIndex.url(environment.id)"
              />
            </div>
          </template>

          <UTable
            :data="applicationSecrets"
            :columns="secretColumns"
            :loading="!application || !secrets"
            empty="This application uses no secrets."
          >
            <template #name-cell="{ row }">
              <span class="font-mono text-sm text-highlighted">{{ row.original.name }}</span>
            </template>
            <template #driver-cell="{ row }">
              <StateBadge
                v-if="row.original.missing"
                label="Missing"
                color="error"
              />
              <span
                v-else
                class="text-muted"
                >{{ row.original.driver ?? '–' }}</span
              >
            </template>
            <template #updated_at-cell="{ row }">
              <span class="text-muted">{{
                row.original.updated_at ? formatRelative(row.original.updated_at) : '–'
              }}</span>
            </template>
            <template #actions-cell="{ row }">
              <UButton
                v-if="row.original.missing"
                label="Create secret"
                size="sm"
                @click="creatingSecret = row.original.name"
              />
            </template>
          </UTable>
        </UCard>

        <p class="text-sm text-muted">Secret values stay on the host. Pods only shows where they are used.</p>
      </div>

      <div
        v-else-if="tab === 'files'"
        class="flex flex-col gap-4"
      >
        <HostError
          v-if="files?.error"
          :error="files.error"
        />

        <UCard
          v-for="file in files?.data ?? []"
          :key="file.path"
          :ui="{ body: 'p-0 sm:p-0' }"
        >
          <template #header>
            <div class="flex items-center gap-3">
              <div class="flex min-w-0 flex-1 flex-col">
                <span class="font-mono text-sm font-medium text-highlighted">{{ file.name }}</span>
                <span class="truncate font-mono text-xs text-muted">{{ file.path }}</span>
              </div>
              <UButton
                label="Edit"
                icon="i-lucide-pencil"
                color="neutral"
                variant="outline"
                size="sm"
                @click="editingFile = file"
              />
            </div>
          </template>

          <pre class="overflow-x-auto p-4 font-mono text-xs leading-5">{{ file.content }}</pre>
        </UCard>

        <USkeleton
          v-if="!files"
          class="h-64"
        />
      </div>

      <UCard
        v-else
        :ui="{ body: 'p-0 sm:p-0' }"
      >
        <HostError
          v-if="logs?.error"
          :error="logs.error"
          class="m-4"
        />
        <JournalLog
          v-else-if="logs?.data"
          :entries="logs.data"
        />
        <USkeleton
          v-else
          class="m-4 h-64"
        />
      </UCard>

      <p class="text-sm text-muted">
        <Link :href="applicationsIndex.url(environment.id)">Back to applications</Link>
      </p>

      <QuadletEditModal
        v-model:open="isEditingFile"
        :environment="environment"
        :application="name"
        :file="editingFile"
      />

      <SecretCreateModal
        v-if="summary"
        v-model:open="isCreatingSecret"
        :environment="environment"
        :applications="[summary]"
        :prefill="{ name: creatingSecret, application: name }"
      />

      <ApplicationOnDemandModal
        v-model:open="isConfiguringOnDemand"
        :environment="environment"
        :application="name"
        :suggestion="ondemandSuggestion?.data ?? null"
        :current="onDemand?.settings ?? null"
      />

      <ConfirmModal
        v-model:open="isDisablingOnDemand"
        :title="`Run ${name} all the time?`"
        description="Removes its socket and idle units, and starts it again, at boot too."
        confirm-label="Turn off on demand"
        :processing="isTurningOffOnDemand"
        @confirm="onDisableOnDemand"
      />

      <ApplicationForceStopModal
        v-model:open="isForceStopping"
        :environment="environment"
        :application="name"
      />

      <ApplicationRemoveModal
        v-model:open="isRemoving"
        :environment="environment"
        :application="name"
      />
    </template>
  </UDashboardPanel>
</template>
