<script setup lang="ts">
import type { JournalEntryData } from '@/types'

defineProps<{
  entries: JournalEntryData[]
}>()

const priorityClass = (priority: number): string => {
  if (priority <= 3) {
    return 'text-error'
  }

  return priority === 4 ? 'text-warning' : 'text-default'
}

const time = (value: string): string => new Date(value).toLocaleTimeString(undefined, { hour12: false })
</script>

<template>
  <pre class="overflow-x-auto p-4 font-mono text-xs leading-5"><template
    v-for="entry in entries"
    :key="`${entry.time}-${entry.message}`"
  ><span class="text-muted">{{ time(entry.time) }}</span> <span class="text-primary">{{ entry.unit }}</span> <span :class="priorityClass(entry.priority)">{{ entry.message }}</span>
</template></pre>
</template>
