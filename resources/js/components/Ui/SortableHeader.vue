<script setup lang="ts">
import type { SortDirection } from '@/utils/sorting'
import { computed } from 'vue'

const props = defineProps<{
  label: string
  direction: SortDirection | null
}>()

defineEmits<{ toggle: [] }>()

const icon = computed(() => {
  if (props.direction === 'asc') {
    return 'i-lucide-arrow-up'
  }

  if (props.direction === 'desc') {
    return 'i-lucide-arrow-down'
  }

  return 'i-lucide-arrow-up-down'
})

const ariaLabel = computed(() => {
  if (props.direction === 'asc') {
    return `${props.label}, sorted ascending`
  }

  if (props.direction === 'desc') {
    return `${props.label}, sorted descending`
  }

  return `Sort by ${props.label}`
})
</script>

<template>
  <UButton
    :label="label"
    :trailing-icon="icon"
    :aria-label="ariaLabel"
    color="neutral"
    variant="ghost"
    size="sm"
    class="-mx-2.5 font-semibold"
    :ui="{ trailingIcon: direction ? 'text-default' : 'text-dimmed' }"
    @click="$emit('toggle')"
  />
</template>
