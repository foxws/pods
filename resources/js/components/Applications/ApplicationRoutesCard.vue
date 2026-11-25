<script setup lang="ts">
import ApplicationRoutesModal from '@/components/Applications/ApplicationRoutesModal.vue'
import HostError from '@/components/Environments/HostError.vue'
import type { ApplicationRoutesData, EnvironmentData, HostProperty } from '@/types'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  application: string
  routes?: HostProperty<ApplicationRoutesData>
}>()

const data = computed(() => props.routes?.data ?? null)
const active = computed(() =>
  (data.value?.routes ?? [])
    .filter((route) => route.host !== null)
    .map((route) => ({ ...route, viaSocket: route.target !== null && route.target !== route.upstream })),
)
const offered = computed(() => (data.value?.routes ?? []).filter((route) => route.host === null))

const isEditing = ref(false)
</script>

<template>
  <UCard>
    <template #header>
      <div class="flex items-center justify-between gap-2">
        <h2 class="font-semibold text-highlighted">Routes</h2>
        <UButton
          v-if="data?.proxy_installed"
          :label="active.length ? 'Edit' : 'Add'"
          color="neutral"
          variant="link"
          size="sm"
          @click="isEditing = true"
        />
      </div>
    </template>

    <HostError
      v-if="routes?.error"
      :error="routes.error"
    />
    <div
      v-else-if="data"
      class="flex flex-col gap-3 text-sm"
    >
      <p
        v-if="!data.proxy_installed"
        class="text-muted"
      >
        The Pods proxy isn't installed on this host, so nothing routes hostnames to applications yet. Install it from
        the Proxy section of this host's settings.
      </p>
      <template v-else>
        <UAlert
          v-if="!data.proxy_running"
          color="warning"
          variant="subtle"
          icon="i-lucide-circle-pause"
          title="The proxy isn't running"
          description="The routes apply once pods-proxy starts."
        />
        <ul
          v-if="active.length"
          class="flex flex-col gap-2"
        >
          <li
            v-for="item in active"
            :key="item.role"
            class="flex flex-col"
          >
            <a
              :href="'https://' + item.host"
              target="_blank"
              rel="noopener"
              class="font-mono text-xs break-all text-primary hover:underline"
              >{{ item.host }}</a
            >
            <span class="font-mono text-xs text-muted">&rarr; {{ item.target }}</span>
            <span
              v-if="item.viaSocket"
              class="text-xs text-muted"
              >through its on-demand socket, so a request wakes it</span
            >
          </li>
        </ul>
        <p
          v-else-if="offered.length"
          class="text-muted"
        >
          pods.yml names {{ offered.map((route) => route.role).join(', ') }}. Give
          {{ offered.length === 1 ? 'it a hostname' : 'them hostnames' }} to reach it through the proxy.
        </p>
        <p
          v-else
          class="text-muted"
        >
          No routes yet.
        </p>
      </template>
    </div>
    <USkeleton
      v-else
      class="h-16"
    />

    <ApplicationRoutesModal
      v-model:open="isEditing"
      :environment="environment"
      :application="application"
      :routes="data?.routes ?? []"
      :wildcard="data?.domain ?? null"
      :domain="data?.default_domain ?? null"
    />
  </UCard>
</template>
