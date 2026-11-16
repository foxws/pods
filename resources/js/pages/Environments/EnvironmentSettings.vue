<script setup lang="ts">
import { update } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentSettingsController'
import HostError from '@/components/Environments/HostError.vue'
import SettingsLayout from '@/components/Settings/SettingsLayout.vue'
import type { EnvironmentData, HostProperty, HostSettingsData } from '@/types'
import { formatRelative } from '@/utils/format'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  settings?: HostProperty<HostSettingsData>
}>()

const form = useForm({
  auto_update: false,
  schedule: 'daily',
  rollback: true,
  linger: false,
})

watch(
  () => props.settings?.data,
  (settings) => {
    if (!settings) {
      return
    }

    form.defaults({
      auto_update: settings.auto_update,
      schedule: settings.schedule ?? 'daily',
      rollback: settings.rollback ?? true,
      linger: settings.linger ?? false,
    })
    form.reset()
  },
  { immediate: true },
)

const isReadOnly = computed(() => !props.settings?.data?.editable)

const schedules = ['daily', 'weekly', '*-*-* 04:00', 'Mon..Fri 04:00']

const fieldClass = 'flex max-sm:flex-col justify-between items-start gap-4'

const submit = (): void => {
  form.put(update.url(props.environment.id), { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Settings of ${environment.name}`" />

  <SettingsLayout
    :title="environment.name"
    :description="`Auto-update and defaults for ${environment.user}@${environment.host}. lpod applies them on the host.`"
  >
    <HostError
      v-if="settings?.error"
      :error="settings.error"
    />

    <USkeleton
      v-if="!settings"
      class="h-80"
    />

    <form
      v-else-if="settings.data"
      class="flex flex-col gap-6"
      @submit.prevent="submit"
    >
      <UAlert
        v-if="isReadOnly"
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Update lpod to change these settings"
        description="The lpod on this host has no settings command yet, so Pods shows what lpod host reports and can't change it."
      />

      <UCard>
        <template #header>
          <h2 class="font-semibold text-highlighted">Auto-update</h2>
          <p class="text-sm text-muted">
            <code class="font-mono">podman-auto-update.timer</code> pulls new images for containers with
            <code class="font-mono">AutoUpdate=registry</code>.
          </p>
        </template>

        <div class="flex flex-col gap-4">
          <UFormField
            label="Enable the timer"
            :description="
              settings.data.next_run
                ? `Next run ${formatRelative(settings.data.next_run)}`
                : settings.data.last_run
                  ? `Last run ${formatRelative(settings.data.last_run)}`
                  : undefined
            "
            :error="form.errors.auto_update"
            :class="fieldClass"
          >
            <USwitch
              v-model="form.auto_update"
              :disabled="isReadOnly"
            />
          </UFormField>

          <template v-if="settings.data.schedule !== null">
            <USeparator />

            <UFormField
              label="Schedule"
              description="A systemd OnCalendar expression for the timer."
              :error="form.errors.schedule"
              :class="fieldClass"
            >
              <div class="flex w-full flex-col gap-2 sm:w-64">
                <UInput
                  v-model="form.schedule"
                  :model-modifiers="{ trim: true }"
                  :disabled="isReadOnly || !form.auto_update"
                  class="w-full font-mono"
                  spellcheck="false"
                />
                <div class="flex flex-wrap gap-1">
                  <UButton
                    v-for="schedule in schedules"
                    :key="schedule"
                    :label="schedule"
                    size="xs"
                    color="neutral"
                    :variant="form.schedule === schedule ? 'soft' : 'ghost'"
                    class="font-mono"
                    :disabled="isReadOnly || !form.auto_update"
                    @click="form.schedule = schedule"
                  />
                </div>
              </div>
            </UFormField>

            <USeparator />

            <UFormField
              label="Roll back on failure"
              description="Restore the previous image when a unit fails to start on the new one."
              :error="form.errors.rollback"
              :class="fieldClass"
            >
              <USwitch
                v-model="form.rollback"
                :disabled="isReadOnly || !form.auto_update"
              />
            </UFormField>
          </template>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <h2 class="font-semibold text-highlighted">Defaults for applications</h2>
        </template>

        <div class="flex flex-col gap-4">
          <UFormField
            label="Quadlet directory"
            description="Where podman quadlet install puts the files."
            :class="fieldClass"
          >
            <code class="font-mono text-sm">{{ settings.data.quadlet_dir ?? '–' }}</code>
          </UFormField>

          <USeparator />

          <UFormField
            label="Enable lingering"
            description="Keep user services running after logout (loginctl enable-linger)."
            :error="form.errors.linger"
            :class="fieldClass"
          >
            <USwitch
              v-if="settings.data.linger !== null"
              v-model="form.linger"
              :disabled="isReadOnly"
            />
            <span
              v-else
              class="text-sm text-muted"
              >Unknown</span
            >
          </UFormField>
        </div>
      </UCard>

      <div
        v-if="!isReadOnly"
        class="flex justify-end gap-2"
      >
        <UButton
          v-if="form.isDirty"
          label="Discard"
          color="neutral"
          variant="ghost"
          @click="form.reset()"
        />
        <UButton
          type="submit"
          label="Save changes"
          :loading="form.processing"
          :disabled="!form.isDirty"
        />
      </div>
    </form>
  </SettingsLayout>
</template>
