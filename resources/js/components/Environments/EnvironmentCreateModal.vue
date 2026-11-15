<script setup lang="ts">
import { store } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentController'
import FormModal from '@/components/Ui/FormModal.vue'
import { useForm } from '@inertiajs/vue3'

const open = defineModel<boolean>('open')

const form = useForm({
  name: '',
  host: '',
  port: 22,
  user: '',
})

const onSubmit = (close: () => void) => {
  form.post(store.url(), {
    onSuccess: () => {
      form.reset()
      close()
    },
  })
}
</script>

<template>
  <FormModal
    v-model:open="open"
    title="Add environment"
    description="Pods connects over SSH and pins the host's key on first contact."
    submit-label="Add environment"
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
            autofocus
            class="w-full"
            placeholder="podman-01"
          />
        </UFormField>

        <div class="grid grid-cols-[1fr_6rem] gap-3">
          <UFormField
            label="Host"
            required
            :error="form.errors.host"
          >
            <UInput
              v-model="form.host"
              :model-modifiers="{ trim: true }"
              class="w-full font-mono"
              placeholder="podman-01.home.arpa"
            />
          </UFormField>

          <UFormField
            label="Port"
            :error="form.errors.port"
          >
            <UInputNumber
              v-model="form.port"
              :min="1"
              :max="65535"
              class="w-full"
            />
          </UFormField>
        </div>

        <UFormField
          label="User"
          description="The user whose Quadlets Pods manages. Use root for system-wide services."
          required
          :error="form.errors.user"
        >
          <UInput
            v-model="form.user"
            :model-modifiers="{ trim: true }"
            class="w-full font-mono"
            placeholder="blue"
          />
        </UFormField>
      </div>
    </template>
  </FormModal>
</template>
