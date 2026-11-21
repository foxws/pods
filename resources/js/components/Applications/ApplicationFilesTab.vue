<script setup lang="ts">
import { destroy as removeDropIn } from '@/actions/Modules/Web/Applications/Controllers/ApplicationDropInController'
import ApplicationDropInModal from '@/components/Applications/ApplicationDropInModal.vue'
import HostError from '@/components/Environments/HostError.vue'
import QuadletEditModal from '@/components/Quadlets/QuadletEditModal.vue'
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import type { EnvironmentData, HostProperty, QuadletFileData } from '@/types'
import { dropInQuadlet, isLocalDropIn, isLpodDropIn, takesDropIns } from '@/utils/quadlet'
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  application: string
  files?: HostProperty<QuadletFileData[]>
}>()

const editingFile = ref<QuadletFileData | null>(null)

const isEditingFile = computed({
  get: () => editingFile.value !== null,
  set: (isOpen: boolean) => {
    if (!isOpen) {
      editingFile.value = null
    }
  },
})

const editingDropIn = ref<QuadletFileData | null>(null)

const isAddingDropIn = ref(false)

const isEditingDropIn = computed({
  get: () => editingDropIn.value !== null || isAddingDropIn.value,
  set: (isOpen: boolean) => {
    if (!isOpen) {
      editingDropIn.value = null
      isAddingDropIn.value = false
    }
  },
})

const dropInQuadlets = computed(() => (props.files?.data ?? []).map((file) => file.name).filter(takesDropIns))

const onEditFile = (file: QuadletFileData): void => {
  if (dropInQuadlet(file.name)) {
    editingDropIn.value = file
  } else {
    editingFile.value = file
  }
}

const removingDropIn = ref<string | null>(null)

const isRemovingDropIn = computed({
  get: () => removingDropIn.value !== null,
  set: (isOpen: boolean) => {
    if (!isOpen) {
      removingDropIn.value = null
    }
  },
})

const shouldRestartAfterRemoving = ref(true)

const isRemovingDropInNow = ref(false)

const onRemoveDropIn = (close: () => void): void => {
  router.delete(removeDropIn.url([props.environment.id, props.application]), {
    data: { name: removingDropIn.value, restart: shouldRestartAfterRemoving.value },
    preserveScroll: true,
    onStart: () => (isRemovingDropInNow.value = true),
    onFinish: () => (isRemovingDropInNow.value = false),
    onSuccess: () => close(),
  })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <HostError
      v-if="files?.error"
      :error="files.error"
    />

    <div
      v-if="dropInQuadlets.length"
      class="flex flex-wrap items-center justify-between gap-3"
    >
      <p class="text-sm text-muted">
        Change a Quadlet's keys with a drop-in. Local ones (NAME.local.conf) stay yours through updates.
      </p>
      <UButton
        label="Add drop-in"
        icon="i-lucide-file-plus"
        color="neutral"
        variant="outline"
        size="sm"
        @click="isAddingDropIn = true"
      />
    </div>

    <UCard
      v-for="file in files?.data ?? []"
      :key="file.path"
      :ui="{ body: 'p-0 sm:p-0' }"
    >
      <template #header>
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex min-w-0 flex-1 flex-col">
            <span class="flex items-center gap-2">
              <span class="truncate font-mono text-sm font-medium text-highlighted">{{ file.name }}</span>
              <UBadge
                v-if="isLpodDropIn(file.name)"
                label="lpod"
                color="neutral"
                variant="subtle"
                size="sm"
              />
              <UBadge
                v-else-if="isLocalDropIn(file.name)"
                label="Local"
                color="primary"
                variant="subtle"
                size="sm"
              />
            </span>
            <span class="truncate font-mono text-xs text-muted">{{ file.path }}</span>
          </div>
          <span
            v-if="isLpodDropIn(file.name)"
            class="text-xs text-muted"
            >Written by lpod from this page's settings</span
          >
          <div
            v-else
            class="flex gap-2"
          >
            <UButton
              label="Edit"
              icon="i-lucide-pencil"
              color="neutral"
              variant="outline"
              size="sm"
              @click="onEditFile(file)"
            />
            <UButton
              v-if="dropInQuadlet(file.name)"
              label="Remove"
              icon="i-lucide-trash-2"
              color="error"
              variant="outline"
              size="sm"
              @click="removingDropIn = file.name"
            />
          </div>
        </div>
      </template>

      <pre class="overflow-x-auto p-4 font-mono text-xs leading-5">{{ file.content }}</pre>
    </UCard>

    <USkeleton
      v-if="!files"
      class="h-64"
    />

    <QuadletEditModal
      v-model:open="isEditingFile"
      :environment="environment"
      :application="application"
      :file="editingFile"
    />

    <ApplicationDropInModal
      v-model:open="isEditingDropIn"
      :environment="environment"
      :application="application"
      :quadlets="dropInQuadlets"
      :file="editingDropIn"
    />

    <ConfirmModal
      v-model:open="isRemovingDropIn"
      :title="`Remove ${removingDropIn}?`"
      description="Its keys no longer apply once the unit restarts."
      confirm-label="Remove drop-in"
      :processing="isRemovingDropInNow"
      @confirm="onRemoveDropIn"
    >
      <USwitch
        v-model="shouldRestartAfterRemoving"
        :label="`Restart ${application} afterwards`"
      />
    </ConfirmModal>
  </div>
</template>
