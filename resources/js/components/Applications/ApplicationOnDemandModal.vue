<script setup lang="ts">
import { update } from '@/actions/Modules/Web/Applications/Controllers/ApplicationOnDemandController'
import FormModal from '@/components/Ui/FormModal.vue'
import type { EnvironmentData, OnDemandSettingsData, OnDemandSuggestionData } from '@/types'
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  application: string
  suggestion: OnDemandSuggestionData | null
  current: OnDemandSettingsData | null
}>()

const form = useForm({
  port: null as number | null,
  app_port: null as number | null,
  idle_timeout: '10min',
  workers: [] as string[],
  idle_container: '',
  idle_user: '',
  idle_command: '',
  schedule: '',
  schedule_at: '',
  schedule_env: '',
})

/**
 * Only a Laravel application gets workers, an idle check and a scheduler. Other applications just sleep.
 */
const isLaravel = computed(() => props.suggestion?.laravel ?? false)

const containers = computed(() => props.suggestion?.containers ?? [])

const scheduleItems = computed(() => [
  { label: 'None', value: '' },
  ...containers.value.map((name) => ({ label: name, value: name })),
])

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }

  const settings = props.current ?? props.suggestion?.settings ?? null

  form.defaults({
    port: settings?.port ?? null,
    app_port: settings?.app_port ?? null,
    idle_timeout: settings?.idle_timeout ?? '10min',
    workers: [...(settings?.workers ?? [])],
    idle_container: settings?.idle_check?.container ?? '',
    idle_user: settings?.idle_check?.user ?? '',
    idle_command: settings?.idle_check?.command ?? '',
    schedule: settings?.schedule?.container ?? '',
    schedule_at: (settings?.schedule?.at ?? []).join(', '),
    schedule_env: Object.entries(settings?.schedule?.environment ?? {})
      .map(([key, value]) => `${key}=${value}`)
      .join('\n'),
  })
  form.reset()
  form.clearErrors()
})

const splitList = (value: string, separator: RegExp): string[] =>
  value
    .split(separator)
    .map((item) => item.trim())
    .filter((item) => item !== '')

const firstError = (prefix: string): string | undefined =>
  Object.entries(form.errors).find(([key]) => key === prefix || key.startsWith(`${prefix}.`))?.[1]

const onSubmit = (close: () => void): void => {
  form
    .transform((data) => {
      if (!isLaravel.value) {
        return { port: data.port, app_port: data.app_port, idle_timeout: data.idle_timeout }
      }

      const hasIdleCheck = data.workers.length > 0 && data.idle_command.trim() !== ''

      return {
        ...data,
        idle_container: hasIdleCheck ? data.idle_container || data.workers[0] : '',
        idle_user: hasIdleCheck ? data.idle_user : '',
        idle_command: hasIdleCheck ? data.idle_command : '',
        schedule_at: data.schedule === '' ? [] : splitList(data.schedule_at, /[\s,]+/),
        schedule_env: data.schedule === '' ? [] : splitList(data.schedule_env, /\r?\n/),
      }
    })
    .put(update.url([props.environment.id, props.application]), {
      preserveScroll: true,
      onSuccess: () => close(),
    })
}
</script>

<template>
  <FormModal
    v-model:open="open"
    :title="current ? `On-demand settings of ${application}` : `Run ${application} on demand`"
    description="A socket listens on the port and starts the application on the first request, like cockpit.socket. It stops again after the idle timeout without connections. Turning it on stops the application now."
    :submit-label="current ? 'Save and restart the socket' : 'Turn on'"
    :processing="form.processing"
    @submit="onSubmit"
  >
    <template #body>
      <div class="flex flex-col gap-4">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField
            label="Socket port"
            description="On the host. Send the application's hostname here."
            required
            :error="form.errors.port"
          >
            <UInputNumber
              v-model="form.port"
              :min="1"
              :max="55535"
              :format-options="{ useGrouping: false }"
              placeholder="8000"
              class="w-full"
            />
          </UFormField>

          <UFormField
            label="App port"
            description="Inside its container."
            required
            :error="form.errors.app_port"
          >
            <UInputNumber
              v-model="form.app_port"
              :min="1"
              :max="65535"
              :format-options="{ useGrouping: false }"
              placeholder="8000"
              class="w-full"
            />
          </UFormField>
        </div>

        <UFormField
          label="Idle timeout"
          description="A systemd time span, such as 10min or 1h."
          required
          :error="form.errors.idle_timeout"
        >
          <UInput
            v-model="form.idle_timeout"
            :model-modifiers="{ trim: true }"
            class="w-full font-mono"
          />
        </UFormField>

        <template v-if="isLaravel">
          <USeparator label="Laravel" />

          <UFormField
            label="Workers"
            description="Queue workers (or Horizon) keep running after the app stops, and stop once there's no work left."
            :error="firstError('workers')"
          >
            <USelectMenu
              v-model="form.workers"
              :items="containers"
              multiple
              placeholder="None"
              class="w-full font-mono"
            />
          </UFormField>

          <div
            v-if="form.workers.length"
            class="grid gap-4 sm:grid-cols-[1fr_8rem]"
          >
            <UFormField
              label="Idle check"
              description="Optional. A command that succeeds when no work is left, such as an app:idle command."
              :error="form.errors.idle_command ?? form.errors.idle_container"
            >
              <UInput
                v-model="form.idle_command"
                placeholder="php /app/artisan app:idle"
                class="w-full font-mono"
              />
            </UFormField>
            <UFormField
              label="Run as"
              :error="form.errors.idle_user"
            >
              <UInput
                v-model="form.idle_user"
                placeholder="Default"
                class="w-full font-mono"
              />
            </UFormField>
            <UFormField
              v-if="form.idle_command"
              label="In container"
              class="sm:col-span-2"
            >
              <USelect
                v-model="form.idle_container"
                :items="form.workers"
                placeholder="Pick a worker"
                class="w-full font-mono"
              />
            </UFormField>
          </div>

          <UFormField
            label="Scheduler"
            description="Runs only at the given minutes, and wakes what it needs. Pods offers the container that runs schedule:work through APP_COMMAND."
            :error="form.errors.schedule"
          >
            <USelect
              v-model="form.schedule"
              :items="scheduleItems"
              class="w-full font-mono"
            />
          </UFormField>

          <template v-if="form.schedule">
            <UFormField
              label="Runs at"
              description="*:MM for every hour, HH:MM for every day, in UTC. Separate them with commas."
              :error="firstError('schedule_at')"
            >
              <UInput
                v-model="form.schedule_at"
                placeholder="*:00, 02:00"
                class="w-full font-mono"
              />
            </UFormField>

            <UFormField
              label="Environment"
              description="KEY=VALUE per line, such as the command that runs the due tasks once."
              :error="firstError('schedule_env')"
            >
              <UTextarea
                v-model="form.schedule_env"
                :rows="2"
                autoresize
                class="w-full font-mono"
              />
            </UFormField>
          </template>
        </template>
      </div>
    </template>
  </FormModal>
</template>
