<script setup lang="ts">
import SaveButton from '@/components/Ui/SaveButton.vue'
import FormRow from '@/components/Ui/FormRow.vue'
import { show, update } from '@/actions/Modules/Web/Settings/Controllers/ApplicationSettingsController'
import type { ApplicationSettings } from '@/types'
import { useForm, useHttp, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'

const loaded = ref(false)
const locales = computed(() => usePage().props.locales ?? [])

const http = useHttp<object, ApplicationSettings>({})

const form = useForm<ApplicationSettings>(update(), {
  site_name: '',
  timezone: 'UTC',
  default_locale: 'en-US',
  allow_registration: false,
  host_refresh: 10,
})

const hostRefreshChoices = computed(() => usePage().props.hostRefreshes ?? [])

const onSubmit = () =>
  form.submit({
    preserveScroll: true,
    preserveState: true,
  })

onMounted(async () => {
  const settings = await http.get(show.url())

  form.defaults(settings)
  form.reset()
  loaded.value = true
})
</script>

<template>
  <UForm
    :state="form"
    loading-auto
    @submit="onSubmit"
  >
    <UPageCard
      title="Application"
      description="Settings stored with spatie/laravel-settings, shared by every user."
      variant="naked"
      orientation="vertical"
      :ui="{ body: 'flex w-full flex-col gap-3' }"
    >
      <template #body>
        <USkeleton
          v-if="!loaded"
          class="h-40 w-full rounded-md"
        />

        <template v-else>
          <FormRow
            label="Site name"
            :error="form.errors.site_name"
          >
            <UInput
              v-model="form.site_name"
              class="w-56"
            />
          </FormRow>

          <USeparator />

          <FormRow
            label="Timezone"
            :error="form.errors.timezone"
          >
            <UInput
              v-model="form.timezone"
              class="w-56"
            />
          </FormRow>

          <USeparator />

          <FormRow
            label="Default locale"
            :error="form.errors.default_locale"
          >
            <USelect
              v-model="form.default_locale"
              :items="locales"
              class="w-56"
            />
          </FormRow>

          <USeparator />

          <FormRow
            label="Allow registration"
            description="Let visitors create their own account."
            :error="form.errors.allow_registration"
          >
            <USwitch v-model="form.allow_registration" />
          </FormRow>

          <USeparator />

          <FormRow
            label="Refresh environment pages"
            description="How often open environment pages reload what changed outside Pods, such as CPU, memory and crashed units. Pods' own changes show at once, and a unit that starts or stops is followed every 5 seconds. Each refresh asks the host again, so a short interval means more SSH commands."
            :error="form.errors.host_refresh"
          >
            <USelect
              v-model="form.host_refresh"
              :items="hostRefreshChoices"
              class="w-56"
            />
          </FormRow>
        </template>
      </template>

      <template #footer>
        <SaveButton
          v-if="loaded"
          :form="form"
        />
      </template>
    </UPageCard>
  </UForm>
</template>
