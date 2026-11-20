<script setup lang="ts">
import { certificate, store, update } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentProxyController'
import { show } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import HostError from '@/components/Environments/HostError.vue'
import ProxySettingsFields from '@/components/Environments/ProxySettingsFields.vue'
import { useAuth } from '@/composables/auth'
import { useNavigation } from '@/composables/navigation'
import type {
  EnvironmentData,
  HostProperty,
  ProxyDefaults,
  ProxyMode,
  ProxySettingsData,
  ProxySettingsFormValues,
} from '@/types'
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  proxy?: HostProperty<ProxySettingsData>
  /** What a host without a proxy starts from (Settings, Proxy). */
  defaults: ProxyDefaults
}>()

const { hasRole } = useAuth()
const { settingsUrl } = useNavigation()

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

const data = computed(() => props.proxy?.data ?? null)

/**
 * An installed proxy shows what its host has. One that isn't starts from Pods' defaults.
 */
const fromDefaults = computed(() => data.value !== null && !data.value.installed)

watch(
  data,
  (proxy) => {
    if (!proxy) {
      return
    }

    const source = proxy.installed ? proxy : props.defaults

    form.defaults({
      mode: (source.mode as ProxyMode | null) ?? props.defaults.mode,
      email: source.email ?? '',
      domain: proxy.installed ? (proxy.domain ?? '') : source.mode === 'wildcard' ? (source.domain ?? '') : '',
      dns_provider: source.dns_provider ?? 'cloudflare',
      dns_token: '',
      resolvers: source.resolvers ?? '',
      http_port: source.http_port ?? 80,
      https_port: source.https_port ?? 443,
      image: source.image ?? '',
    })
    form.reset()
  },
  { immediate: true },
)

const status = computed(() => {
  if (!data.value?.installed) {
    return { label: 'Not installed', color: 'neutral' as const }
  }

  return data.value.running
    ? { label: 'Running', color: 'success' as const }
    : { label: 'Stopped', color: 'warning' as const }
})

const submit = (): void => {
  const options = {
    preserveScroll: true,
    onSuccess: () => form.reset('dns_token'),
  }

  form.transform((values) => ({
    ...values,
    email: values.email.trim() || null,
    resolvers: values.resolvers.trim() || null,
    image: values.image.trim() || null,
    dns_token: values.dns_token.trim() || null,
  }))

  if (data.value?.installed) {
    form.put(update.url(props.environment.id), options)
  } else {
    form.post(store.url(props.environment.id), options)
  }
}
</script>

<template>
  <UCard>
    <template #header>
      <div class="flex items-start justify-between gap-2">
        <div>
          <h2 class="font-semibold text-highlighted">Proxy</h2>
          <p class="text-sm text-muted">
            <code class="font-mono">pods-proxy</code> is Caddy. It sends hostnames to the applications on this host, and
            gets their certificates.
          </p>
        </div>
        <div
          v-if="data"
          class="flex shrink-0 items-center gap-2"
        >
          <UBadge
            :label="status.label"
            :color="status.color"
            variant="subtle"
          />
          <UButton
            v-if="data.installed"
            label="Application"
            color="neutral"
            variant="link"
            size="sm"
            :to="show.url([environment.id, 'pods-proxy'])"
          />
        </div>
      </div>
    </template>

    <HostError
      v-if="proxy?.error"
      :error="proxy.error"
    />

    <USkeleton
      v-else-if="!proxy"
      class="h-64"
    />

    <form
      v-else-if="data"
      class="flex flex-col gap-4"
      @submit.prevent="submit"
    >
      <p
        v-if="fromDefaults"
        class="text-sm text-muted"
      >
        Filled in from Pods' proxy defaults. Installing pulls the image and starts the proxy.
        <ULink
          v-if="hasRole('super-admin')"
          :to="settingsUrl('proxy')"
          class="text-primary"
        >
          Change the defaults
        </ULink>
      </p>

      <UAlert
        v-if="data.installed && !data.managed && data.mode"
        color="info"
        variant="subtle"
        icon="i-lucide-file-pen"
        title="The Caddyfile was written by hand"
        description="The form starts from what lpod reads in it. Saving replaces it, and keeps yours next to it as a backup, so move any sites of your own to sites/*.Caddyfile."
      />

      <ProxySettingsFields
        :form="form"
        :dns-token-stored="data.dns_token"
        :unprivileged-port-start="data.rootless ? data.unprivileged_port_start : null"
      />

      <div class="flex flex-wrap items-center justify-end gap-2">
        <UButton
          v-if="data.installed && data.running && form.mode === 'local'"
          label="Root certificate"
          icon="i-lucide-download"
          color="neutral"
          variant="ghost"
          :to="certificate.url(environment.id)"
          external
          class="mr-auto"
        />
        <UButton
          v-if="data.installed && form.isDirty"
          label="Discard"
          color="neutral"
          variant="ghost"
          @click="form.reset()"
        />
        <UButton
          type="submit"
          :label="data.installed ? 'Save proxy' : 'Install proxy'"
          :loading="form.processing"
          :disabled="data.installed && !form.isDirty"
        />
      </div>
    </form>
  </UCard>
</template>
