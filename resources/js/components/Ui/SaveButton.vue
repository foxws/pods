<script setup lang="ts">
withDefaults(
  defineProps<{
    form: { processing: boolean; recentlySuccessful: boolean }
    label?: string
  }>(),
  { label: 'Save changes' },
)
</script>

<template>
  <UButton
    type="submit"
    :label="form.recentlySuccessful ? 'Saved' : label"
    :icon="form.recentlySuccessful ? 'i-lucide-check' : undefined"
    :color="form.recentlySuccessful ? 'success' : 'primary'"
    :loading="form.processing"
  />
</template>
