<script setup lang="ts">
import { store } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import SecretFileButton from '@/components/Secrets/SecretFileButton.vue'
import FormModal from '@/components/Ui/FormModal.vue'
import type { ApplicationData, EnvironmentData } from '@/types'
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  applications: ApplicationData[]
  prefill: { name: string | null; application: string | null }
}>()

type Attachment = { application: string; type: 'env' | 'mount'; target: string }

const form = useForm<{
  name: string
  driver: 'file' | 'pass' | 'shell'
  value: string
  attachments: Attachment[]
  restart: boolean
}>({
  name: '',
  driver: 'file',
  value: '',
  attachments: [],
  restart: true,
})

const drivers = [
  { label: 'file', value: 'file' },
  { label: 'pass', value: 'pass' },
  { label: 'shell', value: 'shell' },
]

const types = [
  { label: 'Environment variable', value: 'env' },
  { label: 'Mounted file', value: 'mount' },
]

const defaultTarget = (name: string): string =>
  name
    .replace(/^[^-]+-/, '')
    .replace(/[^A-Za-z0-9]+/g, '_')
    .toUpperCase()

const isAttached = (application: string): boolean => form.attachments.some((item) => item.application === application)

const toggle = (application: string, attached: boolean | 'indeterminate') => {
  if (attached === true && !isAttached(application)) {
    form.attachments.push({ application, type: 'env', target: defaultTarget(form.name) })

    return
  }

  if (attached !== true) {
    form.attachments = form.attachments.filter((item) => item.application !== application)
  }
}

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }

  form.reset()
  form.clearErrors()

  if (props.prefill.name) {
    form.name = props.prefill.name
  }

  if (props.prefill.application) {
    toggle(props.prefill.application, true)
  }
})

const preview = computed(() =>
  form.attachments.map(
    (item) =>
      `# ${item.application}.container\n+ Secret=${form.name || 'NAME'},type=${item.type},target=${item.target || 'TARGET'}`,
  ),
)

const submitLabel = computed(() => (form.restart && form.attachments.length ? 'Create and restart' : 'Create secret'))

const onSubmit = (close: () => void) => {
  form.post(store.url(props.environment.id), {
    preserveScroll: true,
    onSuccess: () => close(),
    onFinish: () => form.reset('value'),
  })
}
</script>

<template>
  <FormModal
    v-model:open="open"
    title="Create secret"
    description="Store a value with podman secret create and add it to applications."
    :submit-label="submitLabel"
    :processing="form.processing"
    @submit="onSubmit"
  >
    <template #body>
      <div class="flex flex-col gap-4">
        <UFormField
          label="Name"
          required
          :error="form.errors.name"
        >
          <UInput
            v-model="form.name"
            :model-modifiers="{ trim: true }"
            autofocus
            class="w-full font-mono"
            placeholder="ha-mqtt-password"
          />
        </UFormField>

        <UFormField
          label="Driver"
          :error="form.errors.driver"
        >
          <URadioGroup
            v-model="form.driver"
            :items="drivers"
            orientation="horizontal"
            variant="table"
          />
        </UFormField>

        <UFormField
          label="Value"
          description="Sent to the host once. Pods doesn't store it, and you can't view it again."
          required
          :error="form.errors.value"
        >
          <div class="flex flex-col gap-2">
            <UTextarea
              v-model="form.value"
              :rows="3"
              autocomplete="off"
              spellcheck="false"
              class="w-full font-mono"
            />
            <div class="flex items-center gap-2">
              <SecretFileButton
                @load="form.value = $event"
                @error="form.setError('value', $event)"
              />
              <span class="text-xs text-muted">Or use the contents of a text file, such as a key or certificate.</span>
            </div>
          </div>
        </UFormField>

        <UFormField
          v-if="applications.length"
          label="Add to applications"
        >
          <ul class="flex max-h-60 flex-col divide-y divide-default overflow-y-auto rounded-md border border-default">
            <li
              v-for="application in applications"
              :key="application.name"
              class="flex flex-col gap-2 px-3 py-2"
            >
              <UCheckbox
                :model-value="isAttached(application.name)"
                :label="application.name"
                @update:model-value="toggle(application.name, $event)"
              />

              <template
                v-for="(attachment, index) in form.attachments"
                :key="attachment.application"
              >
                <div
                  v-if="attachment.application === application.name"
                  class="grid grid-cols-2 gap-2 pl-6"
                >
                  <USelect
                    v-model="attachment.type"
                    :items="types"
                    size="sm"
                    aria-label="Secret type"
                  />
                  <UInput
                    v-model="attachment.target"
                    size="sm"
                    class="font-mono"
                    :placeholder="attachment.type === 'env' ? 'MQTT_PASSWORD' : '/run/secrets/name'"
                    aria-label="Target"
                    :color="
                      form.errors[`attachments.${index}.target` as keyof typeof form.errors] ? 'error' : undefined
                    "
                  />
                </div>
              </template>
            </li>
          </ul>
        </UFormField>

        <pre
          v-if="preview.length"
          class="overflow-x-auto rounded-md bg-elevated p-3 font-mono text-xs leading-5 text-success"
          >{{ preview.join('\n') }}</pre>

        <USwitch
          v-if="form.attachments.length"
          v-model="form.restart"
          label="Restart these applications afterwards"
        />
      </div>
    </template>
  </FormModal>
</template>
