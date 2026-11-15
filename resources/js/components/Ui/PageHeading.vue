<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex min-w-0 flex-col gap-1">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-semibold tracking-tight text-highlighted">{{ title }}</h1>
        <slot name="badge" />
      </div>

      <p
        v-if="description || $slots.description"
        class="text-muted"
      >
        <slot name="description">{{ description }}</slot>
      </p>
    </div>

    <div
      v-if="$slots.actions"
      class="flex flex-wrap gap-2"
    >
      <slot name="actions" />
    </div>
  </div>
</template>
