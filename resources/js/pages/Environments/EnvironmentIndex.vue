<script setup lang="ts">
import CardHeader from '@/components/Ui/CardHeader.vue'
import { index as applications } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { destroy } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentController'
import EnvironmentCreateModal from '@/components/Environments/EnvironmentCreateModal.vue'
import SettingsLayout from '@/components/Settings/SettingsLayout.vue'
import type { EnvironmentData } from '@/types'
import { formatRelative } from '@/utils/format'
import { Head, Link, router } from '@inertiajs/vue3'
import { useClipboard } from '@vueuse/core'
import { ref } from 'vue'

type EnvironmentRow = EnvironmentData & { fingerprint: string | null }

const props = defineProps<{
  environments: EnvironmentRow[]
  publicKey: string | null
}>()

const isCreating = ref(false)
const removing = ref<EnvironmentRow | null>(null)

const { copy, copied } = useClipboard({ source: () => props.publicKey ?? '' })

const remove = () => {
  if (!removing.value) {
    return
  }

  router.delete(destroy.url(removing.value.id), {
    onFinish: () => {
      removing.value = null
    },
  })
}
</script>

<template>
  <Head title="Environments" />

  <SettingsLayout
    title="Environments"
    description="Each environment is a user on a Podman host that Pods manages over SSH."
  >
    <template #actions>
      <UButton
        icon="i-lucide-plus"
        label="Add environment"
        @click="isCreating = true"
      />
    </template>

    <UCard variant="subtle">
      <template #header>
        <CardHeader title="SSH key of this Pods instance">
          <template #description>
            Add it to <code class="font-mono">~/.ssh/authorized_keys</code> of every user Pods manages.
          </template>
        </CardHeader>
      </template>

      <div
        v-if="publicKey"
        class="flex items-start gap-2"
      >
        <pre class="min-w-0 flex-1 overflow-x-auto rounded-md bg-elevated p-3 font-mono text-xs">{{ publicKey }}</pre>
        <UButton
          :icon="copied ? 'i-lucide-check' : 'i-lucide-copy'"
          color="neutral"
          variant="outline"
          aria-label="Copy public key"
          @click="copy()"
        />
      </div>
      <UAlert
        v-else
        color="warning"
        variant="subtle"
        icon="i-lucide-key-round"
        title="No SSH key yet"
        description="Run php artisan pods:install in the Pods container to generate one."
      />
    </UCard>

    <UEmpty
      v-if="!environments.length"
      icon="i-lucide-server"
      title="No environments yet"
      description="Add an environment for each host and user whose Podman Quadlets you want to manage."
      :actions="[{ label: 'Add environment', icon: 'i-lucide-plus', onClick: () => (isCreating = true) }]"
    />

    <div class="grid gap-3 md:grid-cols-2">
      <UCard
        v-for="environment in environments"
        :key="environment.id"
      >
        <div class="flex items-start justify-between gap-3">
          <Link
            :href="applications.url(environment.id)"
            class="flex min-w-0 items-center gap-3"
          >
            <UIcon
              :name="environment.is_local ? 'i-lucide-house' : 'i-lucide-server'"
              class="size-5 shrink-0 text-primary"
            />
            <span class="flex min-w-0 flex-col">
              <span class="font-medium text-highlighted">{{ environment.name }}</span>
              <span class="truncate font-mono text-xs text-muted"
                >{{ environment.user }}@{{ environment.host }}:{{ environment.port }}</span
              >
            </span>
          </Link>

          <UButton
            icon="i-lucide-trash-2"
            color="neutral"
            variant="ghost"
            :aria-label="`Remove ${environment.name}`"
            @click="removing = environment"
          />
        </div>

        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
          <dt class="text-muted">Podman</dt>
          <dd>{{ environment.podman ?? '–' }} <span class="text-muted">{{ environment.scope ?? '' }}</span></dd>

          <dt class="text-muted">lpod</dt>
          <dd>{{ environment.lpod ?? '–' }}</dd>

          <dt class="text-muted">Last seen</dt>
          <dd>{{ environment.last_seen_at ? formatRelative(environment.last_seen_at) : 'Never' }}</dd>

          <dt class="text-muted">Host key</dt>
          <dd class="font-mono text-xs break-all">{{ environment.fingerprint ?? '–' }}</dd>
        </dl>
      </UCard>
    </div>

    <EnvironmentCreateModal v-model:open="isCreating" />

    <UModal
      :open="removing !== null"
      title="Remove environment"
      :description="`Pods forgets ${removing?.name ?? 'this environment'}. Nothing changes on the host.`"
      :ui="{ footer: 'justify-end' }"
      @update:open="(value: boolean) => !value && (removing = null)"
    >
      <template #footer>
        <UButton
          label="Cancel"
          color="neutral"
          variant="soft"
          @click="removing = null"
        />
        <UButton
          label="Remove"
          color="error"
          @click="remove"
        />
      </template>
    </UModal>
  </SettingsLayout>
</template>
