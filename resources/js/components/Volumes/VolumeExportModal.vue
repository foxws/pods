<script setup lang="ts">
import { store } from '@/actions/Modules/Web/Volumes/Controllers/VolumeExportController'
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import { useHostAction } from '@/composables/hostActions'
import type { EnvironmentData } from '@/types'
import { computed, ref, watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  /** One volume, or else every volume of the application. */
  volume?: string | null
  application?: string | null
  /** The applications that use what's exported, which can stop meanwhile. */
  applications: string[]
}>()

const stopApplications = ref(false)

watch(open, () => {
  stopApplications.value = false
})

const title = computed(() =>
  props.volume ? `Export ${props.volume}` : `Export the volumes of ${props.application ?? 'the application'}`,
)

const { send, processing } = useHostAction()

const confirm = (close: () => void): void => {
  const data = props.volume ? { volumes: [props.volume] } : { application: props.application }

  send('post', store.url(props.environment.id), { ...data, stop_applications: stopApplications.value }, close, [
    'exports',
  ])
}
</script>

<template>
  <ConfirmModal
    v-model:open="open"
    :title="title"
    description="Pods copies the contents into a tar archive in the background, and lets you know when it's ready to download."
    confirm-label="Start export"
    :processing="processing"
    @confirm="confirm"
  >
    <UCheckbox
      v-if="applications.length"
      v-model="stopApplications"
      :label="`Stop ${applications.join(', ')} while exporting`"
      description="For a consistent copy of files that change while they run, such as a database. They start again afterwards."
    />
    <p
      v-if="!volume"
      class="text-sm text-muted"
    >
      Volumes backed by a folder on the host, or by NFS, are left out. Each volume is exported on its own, one after
      another.
    </p>
  </ConfirmModal>
</template>
