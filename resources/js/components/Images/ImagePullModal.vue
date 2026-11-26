<script setup lang="ts">
import { store } from '@/actions/Modules/Web/Images/Controllers/ImageController'
import FormModal from '@/components/Ui/FormModal.vue'
import type { EnvironmentData } from '@/types'
import { useLiveVisit } from '@/composables/hostActions'
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
}>()

const form = useForm({ reference: '' })

watch(open, (isOpen) => {
  if (isOpen) {
    form.reset()
    form.clearErrors()
  }
})

const liveVisit = useLiveVisit()

const onSubmit = (close: () => void): void => {
  form.post(store.url(props.environment.id), {
    ...liveVisit(),
    onSuccess: () => close(),
  })
}
</script>

<template>
  <FormModal
    v-model:open="open"
    title="Pull image"
    :description="`Pulls into the ${environment.scope ?? 'local'} store for ${environment.user}.`"
    :submit-label="form.processing ? 'Pulling…' : 'Pull image'"
    :processing="form.processing"
    @submit="onSubmit"
  >
    <template #body>
      <UFormField
        label="Image reference"
        description="Short names resolve through registries.conf."
        required
        :error="form.errors.reference"
      >
        <UInput
          v-model="form.reference"
          :model-modifiers="{ trim: true }"
          autofocus
          placeholder="codeberg.org/forgejo/forgejo:13.1"
          class="w-full font-mono"
        />
      </UFormField>
    </template>
  </FormModal>
</template>
