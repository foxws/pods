<script setup lang="ts">
import { update as linkSource } from '@/actions/Modules/Web/Applications/Controllers/ApplicationSourceController'
import FormModal from '@/components/Ui/FormModal.vue'
import type { EnvironmentData } from '@/types'
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  application: string
}>()

const form = useForm({
  repository: '',
  reference: 'main',
  path: '',
})

watch(open, (isOpen) => {
  if (isOpen) {
    form.clearErrors()
  }
})

const submit = (close: () => void): void => {
  form.put(linkSource.url([props.environment.id, props.application]), {
    preserveScroll: true,
    onSuccess: () => close(),
  })
}
</script>

<template>
  <FormModal
    v-model:open="open"
    :title="`Link ${application} to Git`"
    description="Pods fetches the Quadlet files of the repository and compares them with the files on the host. The files on the host don't change: the ones that differ show up as changed outside Pods, and you can replace them with the source's or keep them."
    submit-label="Link"
    :processing="form.processing"
    @submit="submit"
  >
    <template #body>
      <div class="flex flex-col gap-4">
        <UFormField
          label="Repository"
          :error="form.errors.repository"
          required
        >
          <UInput
            v-model="form.repository"
            placeholder="https://github.com/owner/repository.git"
            autocomplete="off"
            class="w-full font-mono"
          />
        </UFormField>
        <UFormField
          label="Reference"
          description="A tag, such as v1.2.0, or a branch, such as main."
          :error="form.errors.reference"
          required
        >
          <UInput
            v-model="form.reference"
            autocomplete="off"
            class="w-full font-mono"
          />
        </UFormField>
        <UFormField
          label="Path"
          description="The folder with the Quadlet files, such as a source's path in its pods.yml. Leave it empty for the root."
          :error="form.errors.path"
        >
          <UInput
            v-model="form.path"
            placeholder="containers/quadlets/production"
            autocomplete="off"
            class="w-full font-mono"
          />
        </UFormField>
      </div>
    </template>
  </FormModal>
</template>
