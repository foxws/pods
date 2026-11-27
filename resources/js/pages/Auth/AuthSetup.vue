<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps<{
  action: string
}>()

const form = useForm('post', props.action, {
  code: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const onSubmit = () =>
  form.submit({
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
</script>

<template>
  <Head title="Set up Pods" />

  <UPageCard
    title="Set up Pods"
    description="Create the owner of this Pods instance. The owner manages its environments and can invite others later."
    class="sm:min-w-md"
  >
    <UForm
      :state="form"
      class="flex flex-col gap-4"
      @submit="onSubmit"
    >
      <UFormField
        label="Setup code"
        required
        :error="form.errors.code"
      >
        <template #help>
          Run <code class="font-mono text-highlighted">php artisan pods:setup</code> in the Pods container to see it, or
          find it in Pods' log. The installer prints it too.
        </template>
        <UInput
          v-model="form.code"
          name="code"
          :model-modifiers="{ trim: true }"
          required
          autofocus
          autocomplete="off"
          spellcheck="false"
          placeholder="XXXX-XXXX-XXXX-XXXX"
          class="w-full font-mono"
          size="lg"
        />
      </UFormField>

      <UFormField
        label="Name"
        required
        :error="form.errors.name"
      >
        <UInput
          v-model="form.name"
          name="name"
          :model-modifiers="{ trim: true }"
          required
          autocomplete="name"
          class="w-full"
          size="lg"
        />
      </UFormField>

      <UFormField
        label="Email"
        required
        :error="form.errors.email"
      >
        <UInput
          v-model="form.email"
          name="email"
          :model-modifiers="{ trim: true }"
          type="email"
          required
          autocomplete="email"
          placeholder="email@example.com"
          class="w-full"
          size="lg"
        />
      </UFormField>

      <UFormField
        label="Password"
        required
        :error="form.errors.password"
      >
        <UInput
          v-model="form.password"
          name="password"
          type="password"
          required
          autocomplete="new-password"
          class="w-full"
          size="lg"
        />
      </UFormField>

      <UFormField
        label="Confirm password"
        required
        :error="form.errors.password_confirmation"
      >
        <UInput
          v-model="form.password_confirmation"
          name="password_confirmation"
          type="password"
          required
          autocomplete="new-password"
          class="w-full"
          size="lg"
        />
      </UFormField>

      <UButton
        type="submit"
        label="Create owner"
        class="justify-center"
        :loading="form.processing"
      />
    </UForm>
  </UPageCard>
</template>
