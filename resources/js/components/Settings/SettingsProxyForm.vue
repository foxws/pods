<script setup lang="ts">
import SaveButton from '@/components/Ui/SaveButton.vue'
import { show, update } from '@/actions/Modules/Web/Settings/Controllers/ProxyDefaultsController'
import ProxySettingsFields from '@/components/Environments/ProxySettingsFields.vue'
import { toProxySettingsPayload, useProxySettingsForm } from '@/composables/proxySettings'
import type { ProxyDefaults } from '@/types'
import { useHttp } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'

const loaded = ref(false)

const http = useHttp<object, ProxyDefaults>({})

const { form, fill } = useProxySettingsForm()

onMounted(async () => {
  fill(await http.get(show.url()))
  loaded.value = true
})

const onSubmit = (): void => {
  form
    .transform((values) => {
      const { dns_token: _token, ...payload } = toProxySettingsPayload(values)

      return payload
    })
    .put(update.url(), {
      preserveScroll: true,
      onSuccess: () => form.defaults(),
    })
}
</script>

<template>
  <form @submit.prevent="onSubmit">
    <UPageCard
      title="Defaults for new environments"
      description="The domain applies to every environment. The rest only fills in an environment's Proxy form until its proxy is installed; after that, the environment keeps its own settings, which you change in that environment's settings."
      variant="naked"
      orientation="vertical"
      :ui="{ body: 'flex w-full flex-col gap-3' }"
    >
      <template #body>
        <USkeleton
          v-if="!loaded"
          class="h-64 w-full rounded-md"
        />

        <ProxySettingsFields
          v-else
          :form="form"
          defaults
        />
      </template>

      <template #footer>
        <SaveButton
          v-if="loaded"
          :form="form"
          label="Save defaults"
          :disabled="!form.isDirty"
        />
      </template>
    </UPageCard>
  </form>
</template>
