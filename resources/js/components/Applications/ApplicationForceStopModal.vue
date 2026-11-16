<script setup lang="ts">
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import { useApplicationOperations } from '@/composables/applications'
import type { EnvironmentData } from '@/types'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  application: string
}>()

const { run } = useApplicationOperations(() => props.environment)

const onConfirm = (close: () => void): void => {
  run(props.application, 'force-stop')
  close()
}
</script>

<template>
  <ConfirmModal
    v-model:open="open"
    :title="`Force stop ${application}`"
    description="Kills every container of the application right away instead of waiting for it to shut down, and clears failed units, so it can start again. Use it when Stop hangs or the application keeps restarting. Work in progress, such as a database write or a running job, is cut off."
    confirm-label="Force stop"
    @confirm="onConfirm"
  />
</template>
