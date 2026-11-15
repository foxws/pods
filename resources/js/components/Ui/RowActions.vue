<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

defineProps<{
  items: DropdownMenuItem[] | DropdownMenuItem[][]
  label: string
}>()
</script>

<template>
  <div class="flex justify-end">
    <UDropdownMenu
      :items="items"
      :content="{ align: 'end' }"
    >
      <UButton
        icon="i-lucide-ellipsis-vertical"
        color="neutral"
        variant="ghost"
        :aria-label="label"
      />
    </UDropdownMenu>
  </div>
</template>
