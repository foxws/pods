<script setup lang="ts">
import FormModal from '@/components/Ui/FormModal.vue'
import { ref, watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  title: string
  description?: string
  confirmLabel: string
  processing?: boolean
  /** The user types this word to unlock the button, for changes that can't be undone. */
  confirmText?: string
  error?: string
}>()

const emit = defineEmits<{
  confirm: [close: () => void]
}>()

const typed = ref('')

watch(open, () => {
  typed.value = ''
})

const onSubmit = (close: () => void): void => {
  if (props.confirmText && typed.value !== props.confirmText) {
    return
  }

  emit('confirm', close)
}
</script>

<template>
  <FormModal
    v-model:open="open"
    :title="title"
    :description="description"
    :submit-label="confirmLabel"
    :processing="processing"
    @submit="onSubmit"
  >
    <template #body>
      <div class="flex flex-col gap-4">
        <slot />

        <UFormField
          v-if="confirmText"
          :error="error"
        >
          <template #label>
            Type <span class="font-mono text-highlighted">{{ confirmText }}</span> to confirm
          </template>
          <UInput
            v-model="typed"
            autocomplete="off"
            spellcheck="false"
            class="w-full font-mono"
          />
        </UFormField>
      </div>
    </template>
  </FormModal>
</template>
