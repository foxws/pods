<script setup lang="ts">
import { show, update } from '@/actions/Modules/Web/Settings/Controllers/ProxyDefaultsController'
import ProxySettingsFields from '@/components/Environments/ProxySettingsFields.vue'
import type { ProxyDefaults, ProxySettingsFormValues } from '@/types'
import { useForm, useHttp } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'

const loaded = ref(false)

const http = useHttp<object, ProxyDefaults>({})

const form = useForm<ProxySettingsFormValues>({
  mode: 'letsencrypt',
  email: '',
  domain: '',
  dns_provider: 'cloudflare',
  dns_token: '',
  resolvers: '',
  http_port: 80,
  https_port: 443,
  image: '',
})

onMounted(async () => {
  const defaults = await http.get(show.url())

  form.defaults({
    mode: defaults.mode,
    email: defaults.email ?? '',
    domain: defaults.domain ?? '',
    dns_provider: defaults.dns_provider,
    dns_token: '',
    resolvers: defaults.resolvers ?? '',
    http_port: defaults.http_port,
    https_port: defaults.https_port,
    image: defaults.image ?? '',
  })
  form.reset()
  loaded.value = true
})

const onSubmit = (): void => {
  form
    .transform(({ dns_token: _token, ...values }) => ({
      ...values,
      email: values.email.trim() || null,
      domain: values.domain.trim() || null,
      resolvers: values.resolvers.trim() || null,
      image: values.image.trim() || null,
    }))
    .put(update.url(), {
      preserveScroll: true,
      onSuccess: () => form.defaults(),
    })
}
</script>

<template>
  <form @submit.prevent="onSubmit">
    <UPageCard
      title="Proxy defaults"
      description="What a host's Proxy section starts from before its proxy is installed, and the domain Pods suggests hostnames under. Hosts with a proxy keep their own settings. The token for a DNS challenge is asked for per host, and only kept there."
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
        <UButton
          v-if="loaded"
          type="submit"
          :label="form.recentlySuccessful ? 'Saved' : 'Save defaults'"
          :icon="form.recentlySuccessful ? 'i-lucide-check' : undefined"
          :color="form.recentlySuccessful ? 'success' : 'primary'"
          :loading="form.processing"
          :disabled="!form.isDirty"
        />
      </template>
    </UPageCard>
  </form>
</template>
