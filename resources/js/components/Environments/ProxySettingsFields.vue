<script setup lang="ts">
import type { ProxySettingsFormValues } from '@/types'
import type { InertiaForm } from '@inertiajs/vue3'
import type { RadioGroupItem } from '@nuxt/ui'
import { computed, ref, watch } from 'vue'

const props = defineProps<{
  form: InertiaForm<ProxySettingsFormValues>
  /** The Pods-wide defaults rather than a host's proxy: no DNS token, and the domain for every mode. */
  defaults?: boolean
  /** Whether the host already has a token for the DNS challenge. */
  dnsTokenStored?: boolean
  /** The lowest port rootless Podman may publish on the host, when it runs rootless. */
  unprivilegedPortStart?: number | null
}>()

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

const isWildcard = computed(() => props.form.mode === 'wildcard')

const defaultImage = computed(() => (isWildcard.value ? 'ghcr.io/foxws/pods-proxy:main' : 'docker.io/library/caddy:2'))

/**
 * Rootless Podman can't publish ports below the host's ip_unprivileged_port_start.
 */
const blockedBelow = computed((): number | null => {
  const start = props.unprivilegedPortStart ?? null
  const lowest =
    props.form.mode === 'http' ? props.form.http_port : Math.min(props.form.http_port, props.form.https_port)

  return start !== null && lowest < start ? start : null
})

/**
 * The advanced fields start open when they hold something other than the defaults, or have an error.
 */
const hasAdvanced = computed(
  () =>
    blockedBelow.value !== null ||
    props.form.http_port !== 80 ||
    (props.form.mode !== 'http' && props.form.https_port !== 443) ||
    props.form.image !== '' ||
    (isWildcard.value && props.form.resolvers !== '') ||
    ['dns_provider', 'resolvers', 'http_port', 'https_port', 'image'].some(
      (field) => props.form.errors[field as keyof ProxySettingsFormValues],
    ),
)

const advancedOpen = ref(hasAdvanced.value)

watch(hasAdvanced, (value) => {
  if (value) {
    advancedOpen.value = true
  }
})

const summary = computed(() => {
  const ports =
    props.form.mode === 'http'
      ? `port ${props.form.http_port}`
      : `ports ${props.form.http_port} and ${props.form.https_port}`

  return `${props.form.image || defaultImage.value} on ${ports}`
})

const fieldClass = 'flex max-sm:flex-col justify-between items-start gap-4'
</script>

<template>
  <div class="flex flex-col gap-4">
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

    <template v-if="isWildcard || defaults">
      <USeparator />

      <UFormField
        label="Domain"
        :description="
          isWildcard
            ? 'The proxy serves *.DOMAIN. Hostnames are one label under it, such as stry-ws.DOMAIN.'
            : 'Hostnames are suggested under it, such as stry.DOMAIN and ws.stry.DOMAIN. Empty for home.arpa.'
        "
        :required="isWildcard && !defaults"
        :error="form.errors.domain"
        :class="fieldClass"
      >
        <UInput
          v-model="form.domain"
          :model-modifiers="{ trim: true }"
          :placeholder="isWildcard ? 'home.example.com' : 'home.arpa'"
          class="w-full font-mono sm:w-64"
          spellcheck="false"
        />
      </UFormField>
    </template>

    <template v-if="isWildcard && !defaults">
      <UFormField
        label="Cloudflare API token"
        :description="
          dnsTokenStored
            ? 'The host has one. Leave this empty to keep it.'
            : 'A token that may edit the DNS records of the domain. Kept as a Podman secret on the host, never in Pods.'
        "
        :required="!dnsTokenStored"
        :error="form.errors.dns_token"
        :class="fieldClass"
      >
        <UInput
          v-model="form.dns_token"
          type="password"
          autocomplete="off"
          :placeholder="dnsTokenStored ? 'Stored on the host' : ''"
          class="w-full font-mono sm:w-64"
        />
      </UFormField>
    </template>

    <template v-if="form.mode === 'letsencrypt' || isWildcard">
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

    <UAlert
      v-if="blockedBelow !== null"
      color="warning"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="`Rootless Podman can't publish ports below ${blockedBelow} here`"
    >
      <template #description>
        Pick ports such as 28080 and 28443 under Advanced, and let the host's main proxy forward to them. Or allow low
        ports on the host:
        <code class="font-mono"
          >echo 'net.ipv4.ip_unprivileged_port_start=80' | sudo tee /etc/sysctl.d/99-lpod.conf</code
        >
      </template>
    </UAlert>

    <USeparator />

    <UCollapsible v-model:open="advancedOpen">
      <UButton
        color="neutral"
        variant="ghost"
        trailing-icon="i-lucide-chevron-down"
        :ui="{ trailingIcon: advancedOpen ? 'rotate-180 transition-transform' : 'transition-transform' }"
        class="-mx-2.5 w-[calc(100%+1.25rem)]"
        block
      >
        <span class="flex flex-1 flex-wrap items-baseline justify-between gap-x-2 text-left">
          <span>Advanced</span>
          <span class="font-mono text-xs font-normal text-muted">{{ summary }}</span>
        </span>
      </UButton>

      <template #content>
        <div class="flex flex-col gap-4 pt-4">
          <template v-if="isWildcard">
            <UFormField
              label="DNS provider"
              :description="
                defaults
                  ? 'Its API token is entered per host, in the host\'s Proxy settings, and kept only on that host.'
                  : undefined
              "
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

            <USeparator />
          </template>

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
                v-if="form.mode !== 'http' || defaults"
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
        </div>
      </template>
    </UCollapsible>
  </div>
</template>
