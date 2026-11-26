<script setup lang="ts">
import CardHeader from '@/components/Ui/CardHeader.vue'
import { store as runStep } from '@/actions/Modules/Web/Applications/Controllers/ApplicationSetupController'
import FormModal from '@/components/Ui/FormModal.vue'
import type { EnvironmentData, InstallationData, ManifestSetupStepData, SetupOutput } from '@/types'
import { formatRelative } from '@/utils/format'
import { useLiveVisit } from '@/composables/hostActions'
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  application: string
  installation: InstallationData
}>()

type SetupResult = InstallationData['setup'][string]

type StepSection = {
  key: string
  title: string | null
  hint: string
  steps: ManifestSetupStepData[]
  since: string | null
}

const installSteps = computed(() => props.installation.manifest?.after_install ?? [])

const updateSteps = computed(() =>
  props.installation.updated_from ? (props.installation.manifest?.after_update ?? []) : [],
)

const sections = computed<StepSection[]>(() => {
  const hasBoth = installSteps.value.length > 0 && updateSteps.value.length > 0

  return [
    {
      key: 'install',
      title: hasBoth ? 'After installing' : null,
      hint: 'Run these once the containers are up. You can run a step again.',
      steps: installSteps.value,
      since: null,
    },
    {
      key: 'update',
      title: hasBoth ? `After the update from ${props.installation.updated_from}` : null,
      hint: `Run these for the update from ${props.installation.updated_from}, once the containers are up.`,
      steps: updateSteps.value,
      since: props.installation.installed_at,
    },
  ].filter((section) => section.steps.length > 0)
})

const allSteps = computed(() => [...installSteps.value, ...updateSteps.value])

/**
 * An update step only counts when it ran since the files were last installed, so every update asks for it again.
 */
const resultOf = (step: ManifestSetupStepData, since: string | null): SetupResult | null => {
  const result = props.installation.setup[step.name]

  return result && (since === null || new Date(result.ran_at) >= new Date(since)) ? result : null
}

const isDone = (step: ManifestSetupStepData, since: string | null): boolean => resultOf(step, since)?.exit_code === 0

const isSectionFinished = (section: StepSection): boolean => section.steps.every((step) => isDone(step, section.since))

const isUpdatePending = computed(() =>
  sections.value.some((section) => section.key === 'update' && !isSectionFinished(section)),
)

const isFinished = computed(() => sections.value.every(isSectionFinished))

const heading = computed(() => {
  if (isUpdatePending.value) {
    return 'Finish update'
  }

  return isFinished.value ? 'Setup' : 'Finish setup'
})

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

const statusOf = (
  step: ManifestSetupStepData,
  since: string | null,
): { icon: string; class: string; label: string } => {
  const result = resultOf(step, since)

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

const liveVisit = useLiveVisit()

const submit = (step: ManifestSetupStepData, close?: () => void): void => {
  form.post(runStep.url([props.environment.id, props.application, step.name]), {
    ...liveVisit(),
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
      <CardHeader :title="heading">
        <template #actions>
          <UBadge
            v-if="isFinished"
            label="Done"
            color="success"
            variant="subtle"
            size="sm"
          />
        </template>
      </CardHeader>
    </template>

    <div class="flex flex-col gap-3 text-sm">
      <section
        v-for="section in sections"
        :key="section.key"
        class="flex flex-col gap-3"
      >
        <h3
          v-if="section.title"
          class="text-xs font-medium text-muted uppercase"
        >
          {{ section.title }}
        </h3>

        <p
          v-if="!isSectionFinished(section)"
          class="text-muted"
        >
          {{ section.hint }}
        </p>

        <ol class="flex flex-col gap-3">
          <li
            v-for="step in section.steps"
            :key="step.name"
            class="flex items-start gap-3"
          >
            <UIcon
              :name="statusOf(step, section.since).icon"
              class="mt-0.5 size-4 shrink-0"
              :class="statusOf(step, section.since).class"
            />
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
              <span class="font-medium text-highlighted">{{ step.label }}</span>
              <span
                v-if="step.description"
                class="text-muted"
                >{{ step.description }}</span
              >
              <code class="truncate font-mono text-xs text-muted">{{ step.container }}: {{ step.command }}</code>
              <span class="text-xs text-dimmed">{{ statusOf(step, section.since).label }}</span>
            </div>
            <UButton
              :label="resultOf(step, section.since) ? 'Run again' : 'Run'"
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
      </section>

      <div
        v-if="output"
        class="flex flex-col gap-1"
      >
        <span class="text-xs text-muted">
          Output of {{ allSteps.find((step) => step.name === output?.step)?.label ?? output.step }} (exit code
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
