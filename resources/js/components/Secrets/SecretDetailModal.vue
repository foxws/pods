<script setup lang="ts">
import { destroy, update } from '@/actions/Modules/Web/Secrets/Controllers/SecretController'
import FormModal from '@/components/Ui/FormModal.vue'
import type { EnvironmentData, SecretData } from '@/types'
import { formatRelative } from '@/utils/format'
import { router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  secret: SecretData | null
}>()

const form = useForm<{ driver: string; value: string; restart: string[] }>({ driver: 'file', value: '', restart: [] })
const isDeleting = ref(false)
const confirmDelete = ref(false)

watch(open, (isOpen) => {
  if (!isOpen || !props.secret) {
    return
  }

  form.reset()
  form.clearErrors()
  form.driver = props.secret.driver ?? 'file'
  form.restart = [...props.secret.used_by]
  confirmDelete.value = false
})

const generate = (): void => {
  const bytes = crypto.getRandomValues(new Uint8Array(24))

  form.value = btoa(String.fromCharCode(...bytes)).replace(/[+/=]/g, '')
}

const onSubmit = (close: () => void): void => {
  if (!props.secret) {
    return
  }

  form.put(update.url([props.environment.id, props.secret.name]), {
    preserveScroll: true,
    onSuccess: () => close(),
    onFinish: () => form.reset('value'),
  })
}

const remove = (): void => {
  if (!props.secret) {
    return
  }

  if (!confirmDelete.value) {
    confirmDelete.value = true

    return
  }

  router.delete(destroy.url([props.environment.id, props.secret.name]), {
    preserveScroll: true,
    onStart: () => {
      isDeleting.value = true
    },
    onSuccess: () => {
      open.value = false
    },
    onFinish: () => {
      isDeleting.value = false
    },
  })
}

const toggleRestart = (application: string, checked: boolean | 'indeterminate'): void => {
  form.restart = checked === true ? [...form.restart, application] : form.restart.filter((item) => item !== application)
}
</script>

<template>
  <FormModal
    v-model:open="open"
    :title="secret?.name ?? 'Secret'"
    description="Secret values can't be read back from Podman. Replace it to rotate."
    :submit-label="form.restart.length ? 'Replace and restart' : 'Replace secret'"
    :processing="form.processing"
    @submit="onSubmit"
  >
    <template #body>
      <div
        v-if="secret"
        class="flex flex-col gap-4"
      >
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
          <dt class="text-muted">Driver</dt>
          <dd>{{ secret.driver ?? 'file' }}</dd>
          <dt class="text-muted">Updated</dt>
          <dd>{{ formatRelative(secret.updated_at) }}</dd>
          <dt class="text-muted">Used by</dt>
          <dd>{{ secret.used_by.join(', ') || 'Unused' }}</dd>
        </dl>

        <UFormField
          label="New value"
          required
          :error="form.errors.value"
        >
          <div class="flex gap-2">
            <UInput
              v-model="form.value"
              type="password"
              autocomplete="off"
              placeholder="Enter a new value"
              class="flex-1 font-mono"
            />
            <UButton
              label="Generate"
              color="neutral"
              variant="outline"
              @click="generate"
            />
          </div>
        </UFormField>

        <div
          v-if="secret.used_by.length"
          class="flex flex-col gap-2"
        >
          <p class="text-sm font-medium text-highlighted">Restart the applications that use it</p>
          <UCheckbox
            v-for="application in secret.used_by"
            :key="application"
            :model-value="form.restart.includes(application)"
            :label="application"
            @update:model-value="toggleRestart(application, $event)"
          />
        </div>

        <UButton
          :label="confirmDelete ? `Delete ${secret.name} for good` : 'Delete secret'"
          icon="i-lucide-trash-2"
          color="error"
          :variant="confirmDelete ? 'solid' : 'soft'"
          class="self-start"
          :loading="isDeleting"
          @click="remove"
        />
      </div>
    </template>
  </FormModal>
</template>
