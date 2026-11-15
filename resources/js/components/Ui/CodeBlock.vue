<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  title?: string
  subtitle?: string
  content: string
  /** Shows the line numbers next to the code. */
  numbered?: boolean
}>()

const lines = computed(() =>
  props.content
    .replace(/\n$/, '')
    .split('\n')
    .map((text) => ({
      text,
      tone: /^\[.+\]$/.test(text.trim())
        ? 'text-primary'
        : text.startsWith('+')
          ? 'text-success'
          : text.startsWith('-')
            ? 'text-error'
            : text.trim().startsWith('#')
              ? 'text-muted'
              : 'text-default',
    })),
)
</script>

<template>
  <UCard :ui="{ body: 'p-0 sm:p-0', header: 'py-3 sm:py-3' }">
    <template
      v-if="title || $slots.actions"
      #header
    >
      <div class="flex items-center gap-2">
        <UIcon
          name="i-lucide-file-code"
          class="size-4 shrink-0 text-primary"
        />
        <div class="flex min-w-0 flex-1 flex-col">
          <span class="truncate font-mono text-sm font-medium text-highlighted">{{ title }}</span>
          <span
            v-if="subtitle"
            class="truncate font-mono text-xs text-muted"
            >{{ subtitle }}</span
          >
        </div>
        <slot name="actions" />
      </div>
    </template>

    <pre
      class="overflow-x-auto p-4 font-mono text-xs leading-5"
    ><template v-for="(line, index) in lines" :key="index"><span v-if="numbered" class="mr-4 inline-block w-6 text-right text-dimmed select-none">{{ index + 1 }}</span><span :class="line.tone">{{ line.text }}</span>
</template></pre>

    <slot name="footer" />
  </UCard>
</template>
