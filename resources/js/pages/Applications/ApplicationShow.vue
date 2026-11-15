<script setup lang="ts">
import { index as applicationsIndex } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { index as secretsIndex } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import JournalLog from '@/components/Applications/JournalLog.vue'
import UnitStatusBadge from '@/components/Applications/UnitStatusBadge.vue'
import HostError from '@/components/Environments/HostError.vue'
import { useApplicationOperations, useHostPolling } from '@/composables/applications'
import type { ApplicationDetailData, EnvironmentData, HostProperty, JournalEntryData, QuadletFileData } from '@/types'
import { formatBytes, formatPort, formatRelative } from '@/utils/format'
import { isTransitioning } from '@/utils/status'
import { Head, Link } from '@inertiajs/vue3'
import type { BreadcrumbItem, TabsItem } from '@nuxt/ui'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  name: string
  application?: HostProperty<ApplicationDetailData>
  files?: HostProperty<QuadletFileData[]>
  logs?: HostProperty<JournalEntryData[]>
}>()

const detail = computed(() => props.application?.data ?? null)
const summary = computed(() => detail.value?.application ?? null)

const tab = ref('overview')

const tabs: TabsItem[] = [
  { label: 'Overview', value: 'overview', icon: 'i-lucide-layout-dashboard' },
  { label: 'Quadlet files', value: 'files', icon: 'i-lucide-file-code' },
  { label: 'Logs', value: 'logs', icon: 'i-lucide-scroll-text' },
]

const breadcrumb = computed<BreadcrumbItem[]>(() => [
  { label: 'Applications', to: applicationsIndex.url(props.environment.id) },
  { label: props.name },
])

const subtitle = computed(() =>
  [
    summary.value?.description,
    summary.value?.status === 'running' && summary.value.since
      ? `up ${formatRelative(summary.value.since).replace(/ ago$/, '')}`
      : null,
    summary.value?.url?.replace(/^https?:\/\//, ''),
  ]
    .filter(Boolean)
    .join(' · '),
)

useHostPolling(['application', 'logs'], () => !!summary.value && isTransitioning(summary.value.status))

const isRunning = computed(() => summary.value?.status === 'running')

const { run } = useApplicationOperations(() => props.environment)
</script>

<template>
  <Head :title="name" />

  <UDashboardPanel id="application">
    <template #header>
      <UDashboardNavbar>
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>

        <template #title>
          <UBreadcrumb :items="breadcrumb" />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-1">
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-highlighted">{{ name }}</h1>
            <UnitStatusBadge
              v-if="summary"
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
            v-if="isRunning"
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
            :disabled="!summary"
            @click="run(name, 'up')"
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
                  <span class="font-medium text-highlighted">{{ container.name }}</span>
                  <span class="truncate font-mono text-xs text-muted">{{ container.image }}</span>
                </div>
                <span class="w-14 text-right text-sm text-muted">{{
                  container.cpu === null ? '–' : `${container.cpu}%`
                }}</span>
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
            <div class="flex flex-col">
              <span class="font-mono text-sm font-medium text-highlighted">{{ file.name }}</span>
              <span class="font-mono text-xs text-muted">{{ file.path }}</span>
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
    </template>
  </UDashboardPanel>
</template>
