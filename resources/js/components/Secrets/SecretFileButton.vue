<script setup lang="ts">
import { ref } from 'vue'

/** The largest value the secret endpoints accept. */
const maxBytes = 524288

const emit = defineEmits<{
  load: [value: string]
  error: [message: string]
}>()

const input = ref<HTMLInputElement | null>(null)

const onChange = async (event: Event): Promise<void> => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  target.value = ''

  if (!file) {
    return
  }

  if (file.size > maxBytes) {
    emit('error', `${file.name} is larger than 512 KB.`)

    return
  }

  emit('load', await file.text())
}
</script>

<template>
  <span class="contents">
    <input
      ref="input"
      type="file"
      class="sr-only"
      tabindex="-1"
      aria-hidden="true"
      @change="onChange"
    />
    <UButton
      label="From file"
      icon="i-lucide-file-up"
      color="neutral"
      variant="outline"
      @click="input?.click()"
    />
  </span>
</template>
