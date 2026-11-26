<script setup lang="ts">
defineProps<{
  title: string
  /** A line under the title; use the description slot when it holds markup. */
  description?: string
  /** Small monospace text on the right, such as the Quadlet keys a card sets. */
  hint?: string
}>()
</script>

<template>
  <div
    class="flex justify-between gap-2"
    :class="description || $slots.description ? 'items-start' : hint ? 'items-baseline' : 'items-center'"
  >
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <h2 class="font-semibold text-highlighted">{{ title }}</h2>
        <slot name="badge" />
      </div>
      <p
        v-if="description || $slots.description"
        class="text-sm text-muted"
      >
        <slot name="description">{{ description }}</slot>
      </p>
    </div>

    <span
      v-if="hint"
      class="font-mono text-xs text-muted"
      >{{ hint }}</span
    >

    <div
      v-if="$slots.actions"
      class="flex shrink-0 items-center gap-2"
    >
      <slot name="actions" />
    </div>
  </div>
</template>
