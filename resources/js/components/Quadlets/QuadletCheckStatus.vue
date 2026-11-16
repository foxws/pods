<script setup lang="ts">
import StateBadge from '@/components/Ui/StateBadge.vue'
import type { HostProperty, QuadletCheckData } from '@/types'

withDefaults(
  defineProps<{
    result: HostProperty<QuadletCheckData> | null
    checking: boolean
    showErrors?: boolean
  }>(),
  { showErrors: true },
)
</script>

<template>
  <div class="flex flex-col gap-2">
    <StateBadge
      v-if="checking"
      label="Checking"
      color="info"
      busy
    />
    <StateBadge
      v-else-if="result?.error"
      label="Not checked"
      color="warning"
    />
    <StateBadge
      v-else-if="result?.data?.valid"
      label="Valid"
      color="success"
    />
    <StateBadge
      v-else-if="result?.data"
      label="Invalid"
      color="error"
    />

    <ul
      v-if="showErrors && result?.data?.errors.length"
      class="flex max-h-64 flex-col gap-1 overflow-y-auto font-mono text-xs break-all text-error"
    >
      <li
        v-for="error in result.data.errors"
        :key="error"
      >
        {{ error }}
      </li>
    </ul>
  </div>
</template>
