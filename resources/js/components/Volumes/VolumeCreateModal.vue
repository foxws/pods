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

const fields = reactive({
  name: '',
  driver: 'local' as 'local' | 'nfs' | 'image',
  device: '',
  options: '',
  image: '',
  owner: '',
})

const drivers = [
  { label: 'local', value: 'local' },
  { label: 'nfs', value: 'nfs' },
  { label: 'image', value: 'image' },
]

const fileName = computed(() => `${fields.name || 'NAME'}.volume`)

const content = computed(() => {
  const [user, group] = fields.owner.split(':')

  return renderQuadlet({
    Volume: [
      ['VolumeName', fields.name],
      ['Driver', fields.driver === 'image' ? 'image' : null],
      ['Image', fields.driver === 'image' ? fields.image : null],
      ['Type', fields.driver === 'nfs' ? 'nfs' : null],
      ['Device', fields.driver === 'nfs' ? fields.device : null],
      ['Options', fields.driver === 'nfs' ? fields.options || 'addr=,rw' : null],
      ['User', user],
      ['Group', group],
    ],
  })
})

const { form, install } = useQuadletInstall(() => props.environment)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(fields, { name: '', driver: 'local', device: '', options: '', image: '', owner: '' })
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
    title="Create volume"
    description="Writes a .volume quadlet, so the volume is recreated on every boot."
    submit-label="Create volume"
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
            placeholder="uptime-kuma-data"
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

        <template v-if="fields.driver === 'nfs'">
          <UFormField
            label="Device"
            description="The export to mount, as host:/path."
          >
            <UInput
              v-model="fields.device"
              placeholder="nas.home.arpa:/export/media"
              class="w-full font-mono"
            />
          </UFormField>
          <UFormField
            label="Mount options"
            description="Passed to mount with -o."
          >
            <UInput
              v-model="fields.options"
              placeholder="addr=nas.home.arpa,rw,nfsvers=4"
              class="w-full font-mono"
            />
          </UFormField>
        </template>

        <UFormField
          v-else-if="fields.driver === 'image'"
          label="Image"
          description="Mounts the image's filesystem as the volume."
        >
          <UInput
            v-model="fields.image"
            placeholder="docker.io/library/alpine:3.22"
            class="w-full font-mono"
          />
        </UFormField>

        <UFormField
          label="Owner (UID:GID)"
          description="Leave empty to keep the defaults."
        >
          <UInput
            v-model="fields.owner"
            placeholder="1000:1000"
            class="w-full font-mono"
          />
        </UFormField>

        <p
          v-if="form.errors['files.0.name'] || form.errors['files.0.content']"
          class="text-sm text-error"
        >
          {{ form.errors['files.0.name'] ?? form.errors['files.0.content'] }}
        </p>

        <CodeBlock
          :title="fileName"
          :content="content"
        />
      </div>
    </template>
  </FormModal>
</template>
