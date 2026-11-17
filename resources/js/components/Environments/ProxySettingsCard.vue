<script setup lang="ts">
import { certificate, store, update } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentProxyController'
import { show } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import HostError from '@/components/Environments/HostError.vue'
import type { EnvironmentData, HostProperty, ProxySettingsData } from '@/types'
import type { RadioGroupItem } from '@nuxt/ui'
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  proxy?: HostProperty<ProxySettingsData>
}>()

type Mode = 'letsencrypt' | 'local' | 'wildcard' | 'http'

const modes: RadioGroupItem[] = [
  {
    label: "Let's Encrypt",
    value: 'letsencrypt',
    description: 'A certificate per hostname, for a public domain the internet reaches on port 80 or 443.',
  },
  {
    label: 'Local certificate authority',
    value: 'local',
    description:
      'Caddy signs the certificates itself, for names such as stry.home.arpa. Trust its root certificate once.',
  },
  {
    label: 'Wildcard through DNS',
    value: 'wildcard',
    description:
      'One certificate for *.DOMAIN through your DNS provider, for a public domain on a host only your network reaches.',
  },
  {
    label: 'Plain HTTP',
    value: 'http',
    description: 'Another proxy in front of this one handles HTTPS.',
  },
]

const form = useForm({
  mode: 'letsencrypt' as Mode,
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

watch(
  data,
  (proxy) => {
    if (!proxy) {
      return
    }

    form.defaults({
      mode: (proxy.mode as Mode | null) ?? 'letsencrypt',
      email: proxy.email ?? '',
      domain: proxy.domain ?? '',
      dns_provider: proxy.dns_provider ?? 'cloudflare',
      dns_token: '',
      resolvers: proxy.resolvers ?? '',
      http_port: proxy.http_port ?? 80,
      https_port: proxy.https_port ?? 443,
      image: proxy.image ?? '',
    })
    form.reset()
  },
  { immediate: true },
)

const defaultImage = computed(() =>
  form.mode === 'wildcard' ? 'ghcr.io/foxws/pods-proxy:main' : 'docker.io/library/caddy:2',
)

/**
 * Rootless Podman can't publish ports below the host's ip_unprivileged_port_start.
 */
const blockedBelow = computed((): number | null => {
  const start = data.value?.rootless ? data.value.unprivileged_port_start : null
  const lowest = form.mode === 'http' ? form.http_port : Math.min(form.http_port, form.https_port)

  return start !== null && start !== undefined && lowest < start ? start : null
})

const status = computed(() => {
  if (!data.value?.installed) {
    return { label: 'Not installed', color: 'neutral' as const }
  }

  return data.value.running
    ? { label: 'Running', color: 'success' as const }
    : { label: 'Stopped', color: 'warning' as const }
})

const fieldClass = 'flex max-sm:flex-col justify-between items-start gap-4'

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
      <UAlert
        v-if="data.installed && !data.managed && data.mode"
        color="info"
        variant="subtle"
        icon="i-lucide-file-pen"
        title="The Caddyfile was written by hand"
        description="The form starts from what lpod reads in it. Saving replaces it, and keeps yours next to it as a backup, so move any sites of your own to sites/*.Caddyfile."
      />

      <UFormField
        label="Certificates"
        :error="form.errors.mode"
      >
        <URadioGroup
          v-model="form.mode"
          :items="modes"
          variant="table"
        />
      </UFormField>

      <template v-if="form.mode === 'wildcard'">
        <USeparator />

        <UFormField
          label="Domain"
          description="The proxy serves *.DOMAIN. Hostnames are one label under it, such as stry-ws.DOMAIN."
          required
          :error="form.errors.domain"
          :class="fieldClass"
        >
          <UInput
            v-model="form.domain"
            :model-modifiers="{ trim: true }"
            placeholder="home.example.com"
            class="w-full font-mono sm:w-64"
            spellcheck="false"
          />
        </UFormField>

        <UFormField
          label="DNS provider"
          :error="form.errors.dns_provider"
          :class="fieldClass"
        >
          <USelect
            v-model="form.dns_provider"
            :items="[{ label: 'Cloudflare', value: 'cloudflare' }]"
            class="w-full sm:w-64"
          />
        </UFormField>

        <UFormField
          label="API token"
          :description="
            data.dns_token
              ? 'The host has one. Leave this empty to keep it.'
              : 'A token that may edit the DNS records of the domain. Kept as a Podman secret on the host, never in Pods.'
          "
          :required="!data.dns_token"
          :error="form.errors.dns_token"
          :class="fieldClass"
        >
          <UInput
            v-model="form.dns_token"
            type="password"
            autocomplete="off"
            :placeholder="data.dns_token ? 'Stored on the host' : ''"
            class="w-full font-mono sm:w-64"
          />
        </UFormField>

        <UFormField
          label="Resolvers"
          description="DNS servers that check the challenge record, such as 1.1.1.1, when the local ones answer for the domain themselves."
          :error="form.errors.resolvers"
          :class="fieldClass"
        >
          <UInput
            v-model="form.resolvers"
            placeholder="1.1.1.1"
            class="w-full font-mono sm:w-64"
            spellcheck="false"
          />
        </UFormField>
      </template>

      <template v-if="form.mode === 'letsencrypt' || form.mode === 'wildcard'">
        <USeparator />

        <UFormField
          label="Email"
          description="Let's Encrypt writes here about certificates that are about to expire."
          :error="form.errors.email"
          :class="fieldClass"
        >
          <UInput
            v-model="form.email"
            type="email"
            :model-modifiers="{ trim: true }"
            class="w-full sm:w-64"
          />
        </UFormField>
      </template>

      <USeparator />

      <UFormField
        label="Ports"
        description="The host ports the proxy publishes for HTTP and HTTPS."
        :error="form.errors.http_port ?? form.errors.https_port"
        :class="fieldClass"
      >
        <div class="flex w-full gap-2 sm:w-64">
          <UInput
            v-model.number="form.http_port"
            type="number"
            :min="1"
            :max="65535"
            aria-label="HTTP port"
            class="w-full font-mono"
          >
            <template #leading><span class="text-xs text-muted">HTTP</span></template>
          </UInput>
          <UInput
            v-if="form.mode !== 'http'"
            v-model.number="form.https_port"
            type="number"
            :min="1"
            :max="65535"
            aria-label="HTTPS port"
            class="w-full font-mono"
          >
            <template #leading><span class="text-xs text-muted">HTTPS</span></template>
          </UInput>
        </div>
      </UFormField>

      <UAlert
        v-if="blockedBelow !== null"
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        :title="`Rootless Podman can't publish ports below ${blockedBelow} here`"
      >
        <template #description>
          Pick ports such as 28080 and 28443, and let the host's main proxy forward to them. Or allow low ports on the
          host:
          <code class="font-mono"
            >echo 'net.ipv4.ip_unprivileged_port_start=80' | sudo tee /etc/sysctl.d/99-lpod.conf</code
          >
        </template>
      </UAlert>

      <USeparator />

      <UFormField
        label="Image"
        :description="`Leave empty for ${defaultImage}.`"
        :error="form.errors.image"
        :class="fieldClass"
      >
        <UInput
          v-model="form.image"
          :placeholder="defaultImage"
          class="w-full font-mono sm:w-64"
          spellcheck="false"
        />
      </UFormField>

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
