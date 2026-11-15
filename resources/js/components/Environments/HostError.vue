<script setup lang="ts">
defineProps<{
  error: string
}>()
</script>

<template>
  <UAlert
    color="error"
    variant="subtle"
    icon="i-lucide-plug-zap"
    title="Pods could not read this host"
    :description="error"
  />
</template>
