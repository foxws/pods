<script setup lang="ts">
import { destroy } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import type { EnvironmentData } from '@/types'
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  application: string
}>()

const form = useForm({ confirmation: '', volumes: false, images: false, secrets: false })

watch(open, (isOpen) => {
  if (isOpen) {
    form.reset()
    form.clearErrors()
  }
})

const onConfirm = (close: () => void): void => {
  form.confirmation = props.application
  form.delete(destroy.url([props.environment.id, props.application]), { onSuccess: () => close() })
}
</script>

<template>
  <ConfirmModal
    v-model:open="open"
    :title="`Remove ${application}?`"
    description="Stops its containers and removes its Quadlet files. This can't be undone."
    confirm-label="Remove application"
    :confirm-text="application"
    :processing="form.processing"
    :error="form.errors.confirmation"
    @confirm="onConfirm"
  >
    <div class="flex flex-col gap-3">
      <UCheckbox
        v-model="form.volumes"
        label="Also remove its volumes"
        description="Deletes the data they hold."
      />
      <UCheckbox
        v-model="form.images"
        label="Also remove its images"
        description="Only images no other container uses."
      />
      <UCheckbox
        v-model="form.secrets"
        label="Also remove its secrets"
        description="Only secrets no other application uses."
      />
    </div>
  </ConfirmModal>
</template>
