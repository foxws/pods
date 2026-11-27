<script setup lang="ts">
import CardHeader from '@/components/Ui/CardHeader.vue'
import {
  create,
  index as applicationsIndex,
  show,
} from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { index as environmentsIndex } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentController'
import { index as imagesIndex } from '@/actions/Modules/Web/Images/Controllers/ImageController'
import journal from '@/actions/Modules/Web/Journal/Controllers/JournalController'
import { index as secretsIndex } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import UnitStatusBadge from '@/components/Applications/UnitStatusBadge.vue'
import HostError from '@/components/Environments/HostError.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import { useApplicationOperations, useHostPolling } from '@/composables/applications'
import { useLpodActions } from '@/composables/lpod'
import { usePins } from '@/composables/pins'
import type {
  ApplicationData,
  EnvironmentData,
  HostEventData,
  HostProperty,
  HostStatsData,
  ImageData,
  LpodVersionData,
  PinData,
  SecretData,
} from '@/types'
import { describeApplication } from '@/utils/application'
import { attentionItems, type AttentionItem } from '@/utils/attention'
import { formatBytes, formatRelative, formatUptime, percentOf } from '@/utils/format'
import { describeEvent, eventTime } from '@/utils/events'
import { totalSize, unusedImages } from '@/utils/images'
import { pluralize } from '@/utils/application'
import { isTransitioning } from '@/utils/status'
import { Head, Link } from '@inertiajs/vue3'
import type { ButtonProps } from '@nuxt/ui'
import { computed } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  lpod: LpodVersionData
  pinned: PinData[]
  host?: HostProperty<HostStatsData>
  applications?: HostProperty<ApplicationData[]>
  secrets?: HostProperty<SecretData[]>
  images?: HostProperty<ImageData[]>
  events?: HostProperty<HostEventData[]>
}>()

const { updateLpod, checkAgain, isUpdating, isChecking } = useLpodActions(() => props.environment)

const activity = computed(() =>
  (props.events?.data ?? []).slice(0, 8).map((event, index) => ({
    key: `${event.time}-${event.name}-${index}`,
    time: eventTime(event.time),
    ...describeEvent(event),
  })),
)

const reclaimable = computed(() => totalSize(unusedImages(props.images?.data ?? [])))

const stats = computed(() => props.host?.data ?? null)

const hostError = computed(() => props.host?.error ?? props.applications?.error ?? null)
const list = computed(() => props.applications?.data ?? [])

const isBusy = computed(() => list.value.some((application) => isTransitioning(application.status)))

useHostPolling(['host', 'applications', 'events'], isBusy)

const { run } = useApplicationOperations(() => props.environment)
const { unpin } = usePins(() => props.environment)

const pins = computed(() => new Map(props.pinned.map((pin) => [pin.name, pin])))
const pinnedNames = computed(() => new Set(pins.value.keys()))

/** Pins of applications the host no longer has are left out until `pods:sync` forgets them. */
const pinnedApplications = computed(() =>
  list.value.flatMap((application) => {
    const pin = pins.value.get(application.name)

    return pin ? [{ application, pin }] : []
  }),
)

const applicationsPinnedFirst = computed(() => [
  ...pinnedApplications.value.map(({ application }) => application),
  ...list.value.filter((application) => !pinnedNames.value.has(application.name)),
])

const hostLine = computed(() =>
  [
    stats.value?.os,
    stats.value?.kernel ? `kernel ${stats.value.kernel}` : null,
    stats.value?.booted_at ? `up ${formatUptime(stats.value.booted_at)}` : null,
    `user ${props.environment.user}`,
  ]
    .filter(Boolean)
    .join(' · '),
)

const containerLine = computed(() => {
  const failed = list.value.filter((application) => application.status === 'failed').length
  const stopped = list.value.filter((application) => application.status === 'inactive').length

  return [failed ? `${failed} failed` : null, stopped ? `${stopped} stopped` : null].filter(Boolean).join(' · ')
})

const attention = computed(() =>
  props.applications?.data && props.secrets?.data
    ? attentionItems(props.applications.data, props.secrets.data, props.images?.data ?? [])
    : [],
)

type AttentionView = {
  key: string
  icon: string
  color: 'text-error' | 'text-warning' | 'text-info'
  title: string
  description: string
  action: ButtonProps
}

const secretsUrl = (query?: Record<string, string>): string => secretsIndex.url(props.environment.id, { query })

const describe = (item: AttentionItem): AttentionView => {
  if (item.kind === 'failed') {
    return {
      key: `failed-${item.application}`,
      icon: 'i-lucide-circle-x',
      color: 'text-error',
      title: `${item.application} failed`,
      description: item.detail,
      action: item.missingSecret
        ? { label: 'Create secret', to: secretsUrl({ secret: item.missingSecret, application: item.application }) }
        : { label: 'View', to: show.url([props.environment.id, item.application]) },
    }
  }

  if (item.kind === 'missing-secret') {
    return {
      key: `missing-${item.secret}`,
      icon: 'i-lucide-key-round',
      color: 'text-error',
      title: `Missing secret ${item.secret}`,
      description: `Referenced by ${item.usedBy.join(', ')}.`,
      action: { label: 'Create secret', to: secretsUrl({ secret: item.secret }) },
    }
  }

  if (item.kind === 'image-updates') {
    return {
      key: 'image-updates',
      icon: 'i-lucide-circle-arrow-up',
      color: 'text-info',
      title: `${pluralize(item.applications.length, 'application')} with image updates`,
      description: item.applications.join(', '),
      action: { label: 'Review', to: imagesIndex.url(props.environment.id) },
    }
  }

  return {
    key: `unused-${item.secret}`,
    icon: 'i-lucide-key-round',
    color: 'text-warning',
    title: `Unused secret ${item.secret}`,
    description: 'No Quadlet references it.',
    action: { label: 'View', to: secretsUrl() },
  }
}

const attentionViews = computed(() => attention.value.map(describe))

const isAttentionLoading = computed(() => !props.applications || !props.secrets || !props.images)

const podmanLine = computed(() =>
  [
    props.environment.podman ? `Podman ${props.environment.podman}` : null,
    props.environment.scope,
    stats.value?.network_backend,
  ]
    .filter(Boolean)
    .join(' · '),
)
</script>

<template>
  <Head title="Overview" />

  <UDashboardPanel id="overview">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading :title="environment.hostname ?? environment.name">
        <template #description>
          <span v-if="stats">{{ hostLine }}</span>
          <USkeleton
            v-else-if="!host"
            class="h-5 w-80 max-w-full"
          />
        </template>

        <template #actions>
          <UButton
            icon="i-lucide-plus"
            label="Install application"
            :to="create.url(environment.id)"
          />
        </template>
      </PageHeading>

      <template v-if="hostError">
        <HostError :error="hostError" />

        <p class="text-sm text-muted">
          Check the environment under
          <Link
            :href="environmentsIndex.url()"
            class="text-primary"
            >Environments</Link
          >, then reload this page.
        </p>
      </template>

      <div
        v-else
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
      >
        <template v-if="stats">
          <UCard variant="subtle">
            <p class="text-sm text-muted">CPU</p>
            <p class="text-2xl font-semibold text-highlighted">{{ Math.round(stats.cpu_usage) }}%</p>
            <p class="mt-1 text-xs text-muted">
              {{ stats.cpu_cores }} cores<template v-if="stats.cpu_load">
                · load {{ stats.cpu_load.map((value) => value.toFixed(2)).join(' ') }}</template
              >
            </p>
          </UCard>

          <UCard variant="subtle">
            <p class="text-sm text-muted">Memory</p>
            <p class="text-2xl font-semibold text-highlighted">{{ formatBytes(stats.memory_used) }}</p>
            <UProgress
              :model-value="percentOf(stats.memory_used, stats.memory_total)"
              size="xs"
              class="mt-2"
            />
            <p class="mt-1 text-xs text-muted">
              of {{ formatBytes(stats.memory_total)
              }}<template v-if="stats.swap_total"> · swap {{ formatBytes(stats.swap_used) }}</template>
            </p>
          </UCard>

          <UCard variant="subtle">
            <p class="text-sm text-muted">Disk</p>
            <p class="text-2xl font-semibold text-highlighted">{{ formatBytes(stats.disk_used) }}</p>
            <UProgress
              :model-value="percentOf(stats.disk_used, stats.disk_total)"
              size="xs"
              class="mt-2"
            />
            <p class="mt-1 text-xs text-muted">
              of {{ formatBytes(stats.disk_total) }}
              <template v-if="reclaimable">
                ·
                <Link
                  :href="imagesIndex.url(environment.id)"
                  class="text-primary hover:underline"
                  >prune {{ formatBytes(reclaimable) }} unused</Link
                >
              </template>
            </p>
          </UCard>

          <UCard variant="subtle">
            <p class="text-sm text-muted">Containers</p>
            <p class="text-2xl font-semibold text-highlighted">
              {{ stats.containers_running }}
              <span class="text-sm font-normal text-muted">/ {{ stats.containers_total }}</span>
            </p>
            <p class="mt-1 text-xs text-muted">{{ containerLine || 'All running' }}</p>
          </UCard>
        </template>

        <template v-else-if="!host">
          <USkeleton
            v-for="index in 4"
            :key="index"
            class="h-28 rounded-lg"
          />
        </template>
      </div>

      <div
        v-if="!hostError"
        class="grid gap-4 lg:grid-cols-3"
      >
        <div class="flex flex-col gap-4 lg:col-span-2">
          <UCard
            v-if="pinned.length && (!applications || pinnedApplications.length)"
            :ui="{ body: 'p-0 sm:p-0' }"
          >
            <template #header>
              <CardHeader title="Pinned" />
            </template>

            <ul
              v-if="applications"
              class="divide-y divide-default"
            >
              <li
                v-for="{ application, pin } in pinnedApplications"
                :key="pin.id"
                class="flex items-center gap-3 px-4 py-3"
              >
                <Link
                  :href="show.url([environment.id, application.name])"
                  class="flex min-w-0 flex-1 flex-col"
                >
                  <span class="truncate font-medium text-highlighted">{{ application.name }}</span>
                  <span class="truncate text-xs text-muted">{{ describeApplication(application) }}</span>
                </Link>

                <UnitStatusBadge :status="application.status" />

                <div class="flex shrink-0 gap-1">
                  <UTooltip
                    v-if="application.status === 'running'"
                    text="Stop"
                  >
                    <UButton
                      icon="i-lucide-square"
                      :aria-label="`Stop ${application.name}`"
                      color="neutral"
                      variant="ghost"
                      size="sm"
                      @click="run(application.name, 'down')"
                    />
                  </UTooltip>
                  <UTooltip
                    v-else
                    text="Start"
                  >
                    <UButton
                      icon="i-lucide-play"
                      :aria-label="`Start ${application.name}`"
                      color="neutral"
                      variant="ghost"
                      size="sm"
                      :disabled="isTransitioning(application.status)"
                      @click="run(application.name, 'up')"
                    />
                  </UTooltip>

                  <UTooltip text="Restart">
                    <UButton
                      icon="i-lucide-rotate-cw"
                      :aria-label="`Restart ${application.name}`"
                      color="neutral"
                      variant="ghost"
                      size="sm"
                      :disabled="application.status !== 'running'"
                      @click="run(application.name, 'restart')"
                    />
                  </UTooltip>

                  <UTooltip text="Unpin">
                    <UButton
                      icon="i-lucide-pin-off"
                      :aria-label="`Unpin ${application.name}`"
                      color="neutral"
                      variant="ghost"
                      size="sm"
                      @click="unpin(pin)"
                    />
                  </UTooltip>
                </div>
              </li>
            </ul>
            <div
              v-else
              class="flex flex-col gap-3 p-4"
            >
              <USkeleton
                v-for="pin in pinned"
                :key="pin.id"
                class="h-10"
              />
            </div>
          </UCard>

          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <CardHeader title="Needs attention">
                <template
                  v-if="attentionViews.length"
                  #badge
                >
                  <UBadge
                    :label="String(attentionViews.length)"
                    color="error"
                    variant="subtle"
                    size="sm"
                  />
                </template>
              </CardHeader>
            </template>

            <ul
              v-if="attentionViews.length"
              class="divide-y divide-default"
            >
              <li
                v-for="item in attentionViews"
                :key="item.key"
                class="flex items-center gap-3 px-4 py-3"
              >
                <UIcon
                  :name="item.icon"
                  class="size-5 shrink-0"
                  :class="item.color"
                />
                <div class="flex min-w-0 flex-1 flex-col">
                  <span class="font-medium text-highlighted">{{ item.title }}</span>
                  <span class="truncate text-sm text-muted">{{ item.description }}</span>
                </div>
                <UButton
                  v-bind="item.action"
                  color="neutral"
                  variant="outline"
                  size="sm"
                />
              </li>
            </ul>
            <div
              v-else-if="isAttentionLoading"
              class="flex flex-col gap-3 p-4"
            >
              <USkeleton
                v-for="index in 2"
                :key="index"
                class="h-10"
              />
            </div>
            <p
              v-else-if="secrets?.error"
              class="p-4 text-sm text-muted"
            >
              Pods could not read the secrets on this host: {{ secrets.error }}
            </p>
            <p
              v-else
              class="flex items-center gap-2 p-4 text-sm text-muted"
            >
              <UIcon
                name="i-lucide-circle-check"
                class="size-4 text-success"
              />
              Nothing needs attention.
            </p>
          </UCard>

          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <CardHeader title="Recent activity">
                <template #actions>
                  <UButton
                    label="Open journal"
                    color="neutral"
                    variant="link"
                    size="sm"
                    :to="journal.url(environment.id)"
                  />
                </template>
              </CardHeader>
            </template>

            <ul
              v-if="activity.length"
              class="divide-y divide-default"
            >
              <li
                v-for="item in activity"
                :key="item.key"
                class="flex items-center gap-3 px-4 py-2.5 text-sm"
              >
                <span class="w-20 shrink-0 text-muted tabular-nums">{{ item.time }}</span>
                <UIcon
                  :name="item.icon"
                  class="size-4 shrink-0"
                  :class="item.color"
                />
                <span class="min-w-0 truncate">
                  <span class="font-medium text-highlighted">{{ item.subject }}</span>
                  {{ item.summary }}
                </span>
              </li>
            </ul>
            <USkeleton
              v-else-if="!events"
              class="m-4 h-24"
            />
            <p
              v-else-if="events.error"
              class="p-4 text-sm text-muted"
            >
              {{ events.error }}
            </p>
            <p
              v-else
              class="p-4 text-sm text-muted"
            >
              Nothing happened to containers or images in the last 24 hours.
            </p>
          </UCard>

          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <CardHeader title="Applications">
                <template #actions>
                  <UButton
                    label="View all"
                    color="neutral"
                    variant="link"
                    size="sm"
                    :to="applicationsIndex.url(environment.id)"
                  />
                </template>
              </CardHeader>
            </template>

            <ul
              v-if="applications"
              class="divide-y divide-default"
            >
              <li
                v-for="application in applicationsPinnedFirst"
                :key="application.name"
              >
                <Link
                  :href="show.url([environment.id, application.name])"
                  class="flex items-center gap-3 px-4 py-3 hover:bg-elevated/50"
                >
                  <span class="flex min-w-0 flex-1 flex-col">
                    <span class="flex min-w-0 items-center gap-1.5 font-medium text-highlighted">
                      <span class="truncate">{{ application.name }}</span>
                      <UIcon
                        v-if="pinnedNames.has(application.name)"
                        name="i-lucide-pin"
                        class="size-3.5 shrink-0 text-muted"
                        aria-label="Pinned"
                      />
                    </span>
                    <span class="truncate text-xs text-muted">{{ describeApplication(application) }}</span>
                  </span>
                  <UnitStatusBadge :status="application.status" />
                </Link>
              </li>
              <li
                v-if="!list.length"
                class="p-4 text-sm text-muted"
              >
                No applications installed yet.
              </li>
            </ul>
            <div
              v-else
              class="flex flex-col gap-3 p-4"
            >
              <USkeleton
                v-for="index in 4"
                :key="index"
                class="h-10"
              />
            </div>
          </UCard>
        </div>

        <UCard>
          <template #header>
            <CardHeader title="System">
              <template #actions>
                <UButton
                  label="Check for updates"
                  color="neutral"
                  variant="link"
                  size="sm"
                  :loading="isChecking"
                  :disabled="isUpdating"
                  @click="checkAgain"
                />
              </template>
            </CardHeader>
          </template>

          <dl
            v-if="stats"
            class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm"
          >
            <dt class="text-muted">Podman</dt>
            <dd>{{ podmanLine || '–' }}</dd>

            <dt class="text-muted">lpod</dt>
            <dd class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span>{{ lpod.installed ?? 'unknown' }}</span>
              <UButton
                v-if="lpod.update_available"
                :label="`Update to ${lpod.required}`"
                icon="i-lucide-download"
                size="xs"
                :loading="isUpdating"
                :disabled="isChecking"
                @click="updateLpod"
              />
              <span
                v-else
                class="text-muted"
                >· up to date</span
              >
            </dd>

            <template v-if="stats.storage_driver">
              <dt class="text-muted">Storage driver</dt>
              <dd>{{ stats.storage_driver }}</dd>
            </template>

            <template v-if="stats.quadlet_dir">
              <dt class="text-muted">Quadlet directory</dt>
              <dd class="font-mono text-xs break-all">{{ stats.quadlet_dir }}</dd>
            </template>

            <template v-if="stats.units_active !== null">
              <dt class="text-muted">Systemd units</dt>
              <dd>
                {{ stats.units_active }} active<template v-if="stats.units_failed">
                  · <span class="text-error">{{ stats.units_failed }} failed</span></template
                >
              </dd>
            </template>

            <dt class="text-muted">Auto-update</dt>
            <dd>
              <template v-if="stats.auto_update_enabled">
                Enabled<template v-if="stats.auto_update_next_run">
                  · next {{ formatRelative(stats.auto_update_next_run) }}</template
                >
              </template>
              <template v-else>Off</template>
            </dd>

            <template v-if="stats.linger !== null">
              <dt class="text-muted">Linger</dt>
              <dd>{{ stats.linger ? 'On, so apps keep running after you log out' : 'Off' }}</dd>
            </template>
          </dl>
          <USkeleton
            v-else-if="!host"
            class="h-40"
          />
        </UCard>
      </div>
    </template>
  </UDashboardPanel>
</template>
