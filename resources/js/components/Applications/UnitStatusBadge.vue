<script setup lang="ts">
import type { UnitStatus } from '@/types'
import { optionLabel } from '@/utils/options'
import { isTransitioning, statusMeta } from '@/utils/status'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  status: UnitStatus
}>()

const meta = computed(() => statusMeta(props.status))
const label = computed(() => optionLabel(usePage().props.unitStatuses, props.status))
</script>

<template>
  <UBadge
    :color="meta.color"
    variant="subtle"
    :label="label"
    :icon="meta.icon"
    :ui="{ leadingIcon: isTransitioning(status) ? 'animate-spin' : undefined }"
  />
</template>
