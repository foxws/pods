<script setup lang="ts">
import { update as acceptFiles } from '@/actions/Modules/Web/Applications/Controllers/ApplicationInstallationController'
import { store as updateApplication } from '@/actions/Modules/Web/Applications/Controllers/ApplicationUpdateController'
import HostError from '@/components/Environments/HostError.vue'
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import type { EnvironmentData, GitTagData, HostProperty, InstallationData } from '@/types'
import { formatRelative } from '@/utils/format'
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  application: string
  installation?: HostProperty<InstallationData | null>
  updates?: HostProperty<GitTagData[]>
}>()

const tracked = computed(() => props.installation?.data ?? null)

const changes = computed(() =>
  tracked.value
    ? [
        { label: 'Modified', names: tracked.value.modified },
        { label: 'Added', names: tracked.value.added },
        { label: 'Removed', names: tracked.value.removed },
      ].filter((change) => change.names.length)
    : [],
)

const isAccepting = ref(false)

const newerTags = computed(() => props.updates?.data ?? [])

/**
 * An application installed from a branch is offered that branch, at its newest commit.
 */
const isBranchUpdate = computed(() => newerTags.value[0]?.name === tracked.value?.reference)

const isConfirmingUpdate = ref(false)
const isUpdating = ref(false)
const updateTag = ref('')
const removeOldFiles = ref(true)
const updateError = ref<string>()

const tagItems = computed(() =>
  newerTags.value.map((tag) => ({ label: `${tag.name} (${tag.commit})`, value: tag.name })),
)

const confirmUpdate = (): void => {
  updateTag.value = newerTags.value[0]?.name ?? ''
  removeOldFiles.value = true
  updateError.value = undefined
  isConfirmingUpdate.value = true
}

const update = (close: () => void): void => {
  router.post(
    updateApplication.url([props.environment.id, props.application]),
    { tag: updateTag.value, remove_old_files: removeOldFiles.value },
    {
      preserveScroll: true,
      onStart: () => (isUpdating.value = true),
      onFinish: () => (isUpdating.value = false),
      onError: (errors) => (updateError.value = errors.tag),
      onSuccess: () => close(),
    },
  )
}

const accept = (): void => {
  router.put(
    acceptFiles.url([props.environment.id, props.application]),
    {},
    {
      preserveScroll: true,
      onStart: () => (isAccepting.value = true),
      onFinish: () => (isAccepting.value = false),
    },
  )
}
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">Source</h2>
    </template>

    <HostError
      v-if="installation?.error"
      :error="installation.error"
    />
    <div
      v-else-if="tracked"
      class="flex flex-col gap-3 text-sm"
    >
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
        <template v-if="tracked.repository">
          <dt class="text-muted">Repository</dt>
          <dd class="min-w-0 break-all">
            <ULink
              :to="tracked.repository"
              target="_blank"
              class="font-mono text-xs"
              >{{ tracked.repository.replace(/^https?:\/\//, '') }}</ULink
            >
          </dd>

          <dt class="text-muted">Reference</dt>
          <dd class="font-mono text-xs break-all">
            {{ tracked.reference }}<template v-if="tracked.path"> · {{ tracked.path }}/</template>
          </dd>

          <template v-if="tracked.commit">
            <dt class="text-muted">Commit</dt>
            <dd class="font-mono text-xs">{{ tracked.commit }}</dd>
          </template>
        </template>
        <template v-else>
          <dt class="text-muted">Files</dt>
          <dd>{{ tracked.installed_at ? 'Uploaded or written in Pods' : 'Installed outside Pods' }}</dd>
        </template>

        <template v-if="tracked.installed_at">
          <dt class="text-muted">Installed</dt>
          <dd :title="tracked.installed_at">{{ formatRelative(tracked.installed_at) }}</dd>
        </template>
      </dl>

      <HostError
        v-if="updates?.error"
        :error="updates.error"
      />
      <div
        v-else-if="newerTags.length"
        class="flex items-center justify-between gap-3 rounded-md bg-elevated px-3 py-2"
      >
        <span v-if="isBranchUpdate">
          <span class="font-medium text-highlighted">{{ newerTags[0]?.name }}</span> has a newer commit,
          <code class="font-mono">{{ newerTags[0]?.commit }}</code>
        </span>
        <span v-else>
          <span class="font-medium text-highlighted">{{ newerTags[0]?.name }}</span>
          is available<template v-if="newerTags.length > 1"> ({{ newerTags.length }} newer tags)</template>
        </span>
        <UButton
          label="Update"
          icon="i-lucide-arrow-up-circle"
          color="primary"
          variant="soft"
          size="xs"
          @click="confirmUpdate"
        />
      </div>

      <UAlert
        v-if="tracked.changed"
        color="warning"
        variant="subtle"
        icon="i-lucide-file-diff"
        title="Changed outside Pods"
        :actions="[
          {
            label: 'Keep these files',
            color: 'warning',
            variant: 'outline',
            size: 'xs',
            loading: isAccepting,
            onClick: accept,
          },
        ]"
        orientation="vertical"
      >
        <template #description>
          <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
            <template
              v-for="change in changes"
              :key="change.label"
            >
              <dt>{{ change.label }}</dt>
              <dd class="font-mono text-xs break-all">{{ change.names.join(', ') }}</dd>
            </template>
          </dl>
        </template>
      </UAlert>
      <p
        v-else
        class="text-muted"
      >
        No changes outside Pods, checked {{ formatRelative(tracked.checked_at) }}.
      </p>
    </div>
    <div
      v-else-if="installation"
      class="flex flex-col gap-3 text-sm"
    >
      <p class="text-muted">
        Installed outside Pods, so Pods doesn't know where it came from or when its files change.
      </p>
      <UButton
        label="Track these files"
        icon="i-lucide-file-check"
        color="neutral"
        variant="outline"
        size="sm"
        class="self-start"
        :loading="isAccepting"
        @click="accept"
      />
    </div>
    <USkeleton
      v-else
      class="h-16"
    />

    <ConfirmModal
      v-model:open="isConfirmingUpdate"
      :title="`Update ${application}?`"
      :description="`Pods fetches the files of ${isBranchUpdate ? `the newest commit of ${updateTag}` : 'the tag'} at the same path, checks them, stops every unit, installs them over the current files and starts the units again.`"
      confirm-label="Update"
      :processing="isUpdating"
      @confirm="update"
    >
      <UFormField
        v-if="!isBranchUpdate"
        label="Tag"
        :error="updateError"
      >
        <USelect
          v-model="updateTag"
          :items="tagItems"
          class="w-full font-mono"
        />
      </UFormField>
      <p
        v-if="isBranchUpdate && updateError"
        class="text-sm text-error"
      >
        {{ updateError }}
      </p>
      <UCheckbox
        v-model="removeOldFiles"
        :label="`Remove files the ${isBranchUpdate ? 'commit' : 'tag'} no longer has`"
        description="Only files Pods installed. Drop-ins you added on the host stay."
      />
      <UAlert
        v-if="tracked?.changed"
        color="warning"
        variant="subtle"
        icon="i-lucide-file-diff"
        title="Files changed outside Pods are replaced"
        description="Keep a copy of your changes, or add them as a drop-in, before updating."
      />
    </ConfirmModal>
  </UCard>
</template>
