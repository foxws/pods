<script setup lang="ts">
import CardHeader from '@/components/Ui/CardHeader.vue'
import { certificate, store, update } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentProxyController'
import { show } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import HostError from '@/components/Environments/HostError.vue'
import ProxySettingsFields from '@/components/Environments/ProxySettingsFields.vue'
import { useAuth } from '@/composables/auth'
import { useNavigation } from '@/composables/navigation'
import { toProxySettingsPayload, useProxySettingsForm } from '@/composables/proxySettings'
import type { EnvironmentData, HostProperty, ProxyDefaults, ProxySettingsData } from '@/types'
import { useLiveVisit } from '@/composables/hostActions'
import { computed, watch } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  proxy?: HostProperty<ProxySettingsData>
  /** What an environment without a proxy starts from (Settings, Proxy). */
  defaults: ProxyDefaults
}>()

const { hasRole } = useAuth()
const { settingsUrl } = useNavigation()

const { form, fill } = useProxySettingsForm()

const data = computed(() => props.proxy?.data ?? null)

/**
 * An installed proxy shows what its environment has. One that isn't starts from Pods' defaults.
 */
const fromDefaults = computed(() => data.value !== null && !data.value.installed)

watch(
  data,
  (proxy) => {
    if (!proxy) {
      return
    }

    if (proxy.installed) {
      fill({ ...proxy, mode: proxy.mode ?? props.defaults.mode })

      return
    }

    /** The defaults' domain only names the wildcard site; elsewhere it's for suggestions alone. */
    fill({ ...props.defaults, domain: props.defaults.mode === 'wildcard' ? props.defaults.domain : null })
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

const liveVisit = useLiveVisit()

const submit = (): void => {
  const options = {
    ...liveVisit(),
    onSuccess: () => form.reset('dns_token'),
  }

  form.transform(toProxySettingsPayload)

  if (data.value?.installed) {
    form.put(update.url(props.environment.id), options)
  } else {
    form.post(store.url(props.environment.id), options)
  }
}
</script>

<template>
  <UCard id="proxy">
    <template #header>
      <CardHeader title="Proxy">
        <template #description>
          <code class="font-mono">pods-proxy</code> is Caddy. It sends hostnames to the applications on this host, and
          gets their certificates.
        </template>
        <template
          v-if="data"
          #actions
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
        </template>
      </CardHeader>
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
