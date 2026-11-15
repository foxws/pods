<script setup lang="ts">
defineProps<{
  /** Shows skeletons in place of the cards while host data loads. */
  loading?: boolean
}>()
</script>

<template>
  <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <template v-if="loading">
      <USkeleton
        v-for="index in 4"
        :key="index"
        class="h-24 rounded-lg"
      />
    </template>
    <slot v-else />
  </div>
</template>
