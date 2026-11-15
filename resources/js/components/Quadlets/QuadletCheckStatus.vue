<script setup lang="ts">
import StateBadge from '@/components/Ui/StateBadge.vue'
import type { HostProperty, QuadletCheckData } from '@/types'

defineProps<{
  result: HostProperty<QuadletCheckData> | null
  checking: boolean
}>()
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
      v-if="result?.data?.errors.length"
      class="flex flex-col gap-1 font-mono text-xs text-error"
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
