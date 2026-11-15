<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  /** Smaller text after the value, e.g. "of 16 GB". */
  hint?: string
  /** Draws a meter under the value, from 0 to 100. */
  progress?: number
  color?: 'primary' | 'warning' | 'error' | 'success' | 'neutral'
}>()
</script>

<template>
  <UCard variant="subtle">
    <p class="text-sm text-muted">{{ label }}</p>
    <p
      class="text-2xl font-semibold"
      :class="color && color !== 'neutral' ? `text-${color}` : 'text-highlighted'"
    >
      {{ value }}
      <span
        v-if="hint"
        class="text-sm font-normal text-muted"
        >{{ hint }}</span
      >
    </p>
    <UProgress
      v-if="progress !== undefined"
      :model-value="progress"
      size="xs"
      class="mt-2"
    />
  </UCard>
</template>
