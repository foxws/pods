<script setup lang="ts">
import type { BadgeProps } from '@nuxt/ui'

withDefaults(
  defineProps<{
    label: string
    color?: BadgeProps['color']
    /** Spins the dot, for states that are changing. */
    busy?: boolean
  }>(),
  {
    color: 'neutral',
  },
)
</script>

<template>
  <UBadge
    :color="color"
    variant="subtle"
    class="gap-1.5 whitespace-nowrap"
  >
    <UIcon
      v-if="busy"
      name="i-lucide-loader-circle"
      class="size-3 animate-spin"
    />
    <span
      v-else
      class="size-1.5 rounded-full bg-current"
      aria-hidden="true"
    />
    {{ label }}
  </UBadge>
</template>
