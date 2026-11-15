<script setup lang="ts">
import CodeBlock from '@/components/Ui/CodeBlock.vue'
import FormModal from '@/components/Ui/FormModal.vue'
import { useQuadletInstall } from '@/composables/quadlets'
import type { EnvironmentData } from '@/types'
import { renderQuadlet } from '@/utils/quadlet'
import { computed, reactive, watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
}>()

const defaults = () => ({
  name: '',
  driver: 'bridge' as 'bridge' | 'macvlan' | 'ipvlan',
  subnet: '',
  gateway: '',
  range: '',
  dns: true,
  internal: false,
  ipv6: false,
})

const fields = reactive(defaults())

const drivers = [
  { label: 'bridge', value: 'bridge' },
  { label: 'macvlan', value: 'macvlan' },
  { label: 'ipvlan', value: 'ipvlan' },
]

const fileName = computed(() => `${fields.name || 'NAME'}.network`)

const content = computed(() =>
  renderQuadlet({
    Network: [
      ['NetworkName', fields.name],
      ['Driver', fields.driver === 'bridge' ? null : fields.driver],
      ['Subnet', fields.subnet],
      ['Gateway', fields.gateway],
      ['IPRange', fields.range],
      ['DisableDNS', !fields.dns],
      ['Internal', fields.internal],
      ['IPv6', fields.ipv6],
    ],
  }),
)

const { form, install } = useQuadletInstall(() => props.environment)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(fields, defaults())
    form.clearErrors()
  }
})

const onSubmit = (close: () => void): void => {
  form.application = fields.name
  form.files = [{ name: fileName.value, content: content.value }]
  form.start = true
  install(close)
}
</script>

<template>
  <FormModal
    v-model:open="open"
    title="Create network"
    description="Containers on this network reach each other by name through aardvark-dns."
    submit-label="Create network"
    :processing="form.processing"
    @submit="onSubmit"
  >
    <template #body>
      <div class="flex flex-col gap-4">
        <UFormField
          label="Name"
          required
          :error="form.errors.application"
        >
          <UInput
            v-model="fields.name"
            :model-modifiers="{ trim: true }"
            autofocus
            placeholder="proxy"
            class="w-full font-mono"
          />
        </UFormField>

        <UFormField label="Driver">
          <URadioGroup
            v-model="fields.driver"
            :items="drivers"
            orientation="horizontal"
            variant="table"
          />
        </UFormField>

        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField
            label="Subnet"
            description="Leave empty to pick a free one."
          >
            <UInput
              v-model="fields.subnet"
              placeholder="10.89.4.0/24"
              class="w-full font-mono"
            />
          </UFormField>
          <UFormField label="Gateway">
            <UInput
              v-model="fields.gateway"
              placeholder="10.89.4.1"
              class="w-full font-mono"
            />
          </UFormField>
        </div>

        <UFormField label="IP range">
          <UInput
            v-model="fields.range"
            placeholder="Optional"
            class="w-full font-mono"
          />
        </UFormField>

        <div class="flex flex-col gap-3">
          <USwitch
            v-model="fields.dns"
            label="DNS"
            description="Resolve container names on this network."
          />
          <USwitch
            v-model="fields.internal"
            label="Internal"
            description="No route to the outside world."
          />
          <USwitch
            v-model="fields.ipv6"
            label="IPv6"
          />
        </div>

        <p
          v-if="form.errors['files.0.name']"
          class="text-sm text-error"
        >
          {{ form.errors['files.0.name'] }}
        </p>

        <CodeBlock
          :title="fileName"
          :content="content"
        />
      </div>
    </template>
  </FormModal>
</template>
