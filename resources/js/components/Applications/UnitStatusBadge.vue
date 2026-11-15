<script setup lang="ts">
import type { UnitStatus } from '@/types'
import { isTransitioning, statusMeta } from '@/utils/status'
import { computed } from 'vue'

const props = defineProps<{
  status: UnitStatus
}>()

const meta = computed(() => statusMeta(props.status))
</script>

<template>
  <UBadge
    :color="meta.color"
    variant="subtle"
    :label="meta.label"
    :icon="meta.icon"
    :ui="{ leadingIcon: isTransitioning(status) ? 'animate-spin' : undefined }"
  />
</template>
