<script setup lang="ts">
import QuadletCheckStatus from '@/components/Quadlets/QuadletCheckStatus.vue'
import FormModal from '@/components/Ui/FormModal.vue'
import { useApplicationOperations } from '@/composables/applications'
import { useQuadletCheck, useQuadletInstall } from '@/composables/quadlets'
import type { EnvironmentData, QuadletFileData } from '@/types'
import { computed, ref, watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  application: string
  file: QuadletFileData | null
}>()

const content = ref('')
const shouldRestart = ref(true)

watch(open, (isOpen) => {
  if (!isOpen || !props.file) {
    return
  }

  content.value = props.file.content
  shouldRestart.value = true
  form.clearErrors()
})

const edited = computed(() => (open.value && props.file ? [{ name: props.file.name, content: content.value }] : []))

const { result, checking } = useQuadletCheck(() => props.environment, edited)

const { form, install } = useQuadletInstall(() => props.environment)

const { run } = useApplicationOperations(() => props.environment)

const isUnchanged = computed(() => content.value === props.file?.content)

const contentError = computed(() => form.errors['files.0.content' as keyof typeof form.errors])

const onSubmit = (close: () => void): void => {
  if (!props.file || isUnchanged.value) {
    close()

    return
  }

  form.application = props.application
  form.files = edited.value
  form.start = false
  form.replace = true

  install(() => {
    close()

    if (shouldRestart.value) {
      run(props.application, 'restart')
    }
  })
}
</script>

<template>
  <FormModal
    v-model:open="open"
    :title="file ? `Edit ${file.name}` : 'Edit Quadlet file'"
    :description="file?.path"
    :submit-label="shouldRestart ? 'Save and restart' : 'Save'"
    :processing="form.processing"
    :ui="{ content: 'sm:max-w-3xl' }"
    @submit="onSubmit"
  >
    <template #body>
      <div class="flex flex-col gap-4">
        <UFormField
          label="Content"
          :error="contentError"
        >
          <UTextarea
            v-model="content"
            :rows="18"
            autoresize
            :maxrows="28"
            spellcheck="false"
            autocomplete="off"
            class="w-full"
            :ui="{ base: 'font-mono text-xs leading-5' }"
          />
        </UFormField>

        <QuadletCheckStatus
          :result="result"
          :checking="checking"
        />

        <USwitch
          v-model="shouldRestart"
          :label="`Restart ${application} afterwards`"
          description="systemd only picks up the new unit after a restart."
        />
      </div>
    </template>
  </FormModal>
</template>
