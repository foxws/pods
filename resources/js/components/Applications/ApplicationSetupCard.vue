<script setup lang="ts">
import { store as runStep } from '@/actions/Modules/Web/Applications/Controllers/ApplicationSetupController'
import FormModal from '@/components/Ui/FormModal.vue'
import type { EnvironmentData, InstallationData, ManifestSetupStepData, SetupOutput } from '@/types'
import { formatRelative } from '@/utils/format'
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  application: string
  installation: InstallationData
}>()

const steps = computed(() => props.installation.manifest?.after_install ?? [])

const isFinished = computed(() => steps.value.every((step) => props.installation.setup[step.name]?.exit_code === 0))

const form = useForm<{ inputs: Record<string, string> }>({ inputs: {} })

const running = ref<string | null>(null)
const asking = ref<ManifestSetupStepData | null>(null)
const output = ref<SetupOutput | null>(null)

const isAsking = computed({
  get: () => asking.value !== null,
  set: (open) => {
    if (!open) {
      asking.value = null
    }
  },
})

const statusOf = (step: ManifestSetupStepData): { icon: string; class: string; label: string } => {
  const result = props.installation.setup[step.name]

  if (!result) {
    return { icon: 'i-lucide-circle-dashed', class: 'text-muted', label: 'Not run yet' }
  }

  if (result.exit_code === 0) {
    return { icon: 'i-lucide-circle-check', class: 'text-success', label: `Done ${formatRelative(result.ran_at)}` }
  }

  return {
    icon: 'i-lucide-circle-x',
    class: 'text-error',
    label: `Failed ${formatRelative(result.ran_at)} with exit code ${result.exit_code}`,
  }
}

const submit = (step: ManifestSetupStepData, close?: () => void): void => {
  form.post(runStep.url([props.environment.id, props.application, step.name]), {
    preserveScroll: true,
    onStart: () => (running.value = step.name),
    onFinish: () => (running.value = null),
    onFlash: ({ setup }) => {
      output.value = setup ?? null
    },
    onSuccess: () => {
      form.reset()
      close?.()
    },
  })
}

const run = (step: ManifestSetupStepData): void => {
  output.value = null

  if (!step.inputs.length) {
    form.inputs = {}
    submit(step)

    return
  }

  form.clearErrors()
  form.inputs = Object.fromEntries(step.inputs.map((input) => [input.name, '']))
  asking.value = step
}
</script>

<template>
  <UCard>
    <template #header>
      <div class="flex items-center justify-between gap-2">
        <h2 class="font-semibold text-highlighted">{{ isFinished ? 'Setup' : 'Finish setup' }}</h2>
        <UBadge
          v-if="isFinished"
          label="Done"
          color="success"
          variant="subtle"
          size="sm"
        />
      </div>
    </template>

    <div class="flex flex-col gap-3 text-sm">
      <p
        v-if="!isFinished"
        class="text-muted"
      >
        Run these once the containers are up. You can run a step again.
      </p>

      <ol class="flex flex-col gap-3">
        <li
          v-for="step in steps"
          :key="step.name"
          class="flex items-start gap-3"
        >
          <UIcon
            :name="statusOf(step).icon"
            class="mt-0.5 size-4 shrink-0"
            :class="statusOf(step).class"
          />
          <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="font-medium text-highlighted">{{ step.label }}</span>
            <span
              v-if="step.description"
              class="text-muted"
              >{{ step.description }}</span
            >
            <code class="truncate font-mono text-xs text-muted">{{ step.container }}: {{ step.command }}</code>
            <span class="text-xs text-dimmed">{{ statusOf(step).label }}</span>
          </div>
          <UButton
            :label="installation.setup[step.name] ? 'Run again' : 'Run'"
            icon="i-lucide-play"
            color="neutral"
            variant="outline"
            size="xs"
            :loading="running === step.name"
            :disabled="running !== null && running !== step.name"
            @click="run(step)"
          />
        </li>
      </ol>

      <div
        v-if="output"
        class="flex flex-col gap-1"
      >
        <span class="text-xs text-muted">
          Output of {{ steps.find((step) => step.name === output?.step)?.label ?? output.step }} (exit code
          {{ output.exit_code }})
        </span>
        <pre
          class="max-h-64 overflow-auto rounded-md bg-elevated p-3 font-mono text-xs whitespace-pre-wrap"
          :class="output.exit_code === 0 ? 'text-default' : 'text-error'"
          >{{ output.output || 'No output.' }}</pre>
      </div>
    </div>

    <FormModal
      v-model:open="isAsking"
      :title="asking?.label ?? ''"
      :description="asking?.description ?? undefined"
      submit-label="Run"
      :processing="form.processing"
      @submit="(close) => asking && submit(asking, close)"
    >
      <template #body>
        <div
          v-if="asking"
          class="flex flex-col gap-4"
        >
          <UFormField
            v-for="input in asking.inputs"
            :key="input.name"
            :label="input.label"
            :description="input.description ?? undefined"
            :error="form.errors[`inputs.${input.name}` as keyof typeof form.errors]"
            required
          >
            <UInput
              v-model="form.inputs[input.name]"
              :type="input.type"
              :autocomplete="input.type === 'password' ? 'new-password' : 'off'"
              class="w-full"
            />
          </UFormField>
        </div>
      </template>
    </FormModal>
  </UCard>
</template>
