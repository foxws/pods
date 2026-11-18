<script setup lang="ts">
import { store as runTimer } from '@/actions/Modules/Web/Applications/Controllers/ApplicationTimerController'
import HostError from '@/components/Environments/HostError.vue'
import StateBadge from '@/components/Ui/StateBadge.vue'
import type { EnvironmentData, HostProperty, ManifestTimerData, TimerData } from '@/types'
import { formatRelative } from '@/utils/format'
import { router } from '@inertiajs/vue3'
import type { BadgeProps } from '@nuxt/ui'
import { ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  application: string
  timers?: HostProperty<TimerData[]>
  /** The timers of the stored pods.yml, for their descriptions. */
  manifestTimers: ManifestTimerData[]
}>()

const running = ref<string | null>(null)

const descriptionOf = (name: string): string | null =>
  props.manifestTimers.find((timer) => timer.name === name)?.description ?? null

const lastRun = (timer: TimerData): { label: string; color: BadgeProps['color'] } | null => {
  if (timer.running) {
    return { label: 'Running', color: 'info' }
  }

  if (timer.last_result === null) {
    return null
  }

  return timer.last_result === 'success'
    ? { label: 'Succeeded', color: 'success' }
    : { label: `Failed (${timer.last_result})`, color: 'error' }
}

const run = (timer: TimerData): void => {
  router.post(
    runTimer.url([props.environment.id, props.application, timer.name]),
    {},
    {
      preserveScroll: true,
      onStart: () => (running.value = timer.name),
      onFinish: () => (running.value = null),
    },
  )
}
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">Timers</h2>
    </template>

    <HostError
      v-if="timers?.error"
      :error="timers.error"
    />
    <ul
      v-else-if="timers?.data?.length"
      class="flex flex-col gap-4 text-sm"
    >
      <li
        v-for="timer in timers.data"
        :key="timer.name"
        class="flex flex-col gap-1.5"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="font-mono text-xs break-all">{{ timer.unit }}</span>
          <UButton
            label="Run now"
            icon="i-lucide-play"
            color="neutral"
            variant="outline"
            size="xs"
            :loading="running === timer.name"
            :disabled="timer.running"
            @click="run(timer)"
          />
        </div>
        <p
          v-if="descriptionOf(timer.name)"
          class="text-muted"
        >
          {{ descriptionOf(timer.name) }}
        </p>
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs">
          <dt class="text-muted">Schedule</dt>
          <dd class="font-mono break-all">{{ timer.on_calendar.join(', ') }}</dd>

          <dt class="text-muted">Next run</dt>
          <dd>{{ timer.enabled ? formatRelative(timer.next_run) : 'Not enabled' }}</dd>

          <dt class="text-muted">Last run</dt>
          <dd class="flex flex-wrap items-center gap-2">
            {{ timer.last_run ? formatRelative(timer.last_run) : 'Never' }}
            <StateBadge
              v-if="lastRun(timer)"
              :label="lastRun(timer)!.label"
              :color="lastRun(timer)!.color"
              :busy="timer.running"
            />
          </dd>
        </dl>
      </li>
    </ul>
    <USkeleton
      v-else-if="!timers"
      class="h-16"
    />
    <p
      v-else
      class="text-sm text-muted"
    >
      pods.yml names timers, but none are set up on the host. Update the application to set them up.
    </p>
  </UCard>
</template>
