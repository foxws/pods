<script setup lang="ts">
import { useAuth } from '@/composables/auth'
import { update } from '@/routes/user-profile-information'
import { useForm } from '@inertiajs/vue3'

const { user, logOut } = useAuth()

const form = useForm(update(), {
  name: user.value?.name || '',
  email: user.value?.email || '',
})

const onSubmit = () =>
  form.submit({
    preserveState: true,
    replace: true,
    only: ['auth', 'user'],
  })
</script>

<template>
  <div class="flex flex-col gap-4">
    <UForm
      :state="form"
      class="flex flex-col py-3"
      loading-auto
      @submit="onSubmit"
    >
      <UPageCard
        title="Account"
        description="Update your name and email address."
        variant="naked"
        orientation="vertical"
        :ui="{
          body: 'flex w-full flex-col gap-3',
        }"
      >
        <template #body>
          <UFormField
            label="Name"
            required
            :error="form.errors.name"
          >
            <UInput
              v-model="form.name"
              :model-modifiers="{ string: true, trim: true }"
              autofocus
              autocapitalize="words"
            />
          </UFormField>

          <USeparator />

          <UFormField
            label="Email"
            required
            :error="form.errors.email"
          >
            <UInput
              v-model="form.email"
              :model-modifiers="{ string: true, trim: true }"
            />
          </UFormField>
        </template>

        <template #footer>
          <UButton
            type="submit"
            :label="form.recentlySuccessful ? 'Saved' : 'Save changes'"
            :icon="form.recentlySuccessful ? 'i-lucide-check' : undefined"
            :color="form.recentlySuccessful ? 'success' : 'primary'"
            :loading="form.processing"
          />
        </template>
      </UPageCard>
    </UForm>

    <USeparator />

    <UPageCard
      title="Session"
      description="Log out of your account."
      variant="naked"
      orientation="vertical"
    >
      <template #footer>
        <UButton
          label="Logout"
          color="primary"
          variant="soft"
          @click="logOut"
        />
      </template>
    </UPageCard>
  </div>
</template>
