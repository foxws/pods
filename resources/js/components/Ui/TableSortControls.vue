<script setup lang="ts" generic="T">
import { firstSort, type SortableColumn, type TableSort } from '@/utils/sorting'
import { computed } from 'vue'

/**
 * The sort on phones, where the rows are cards without column headers.
 */
const props = defineProps<{
  columns: SortableColumn<T>[]
}>()

const sort = defineModel<TableSort | null>({ required: true })

// Reka's select items can't have an empty value.
const defaultOrder = 'default'

const items = computed(() => [
  { label: 'Default order', value: defaultOrder },
  ...props.columns.map((column) => ({ label: column.label, value: column.column })),
])

const column = computed({
  get: () => sort.value?.column ?? defaultOrder,
  set: (value: string) => {
    const selected = props.columns.find((item) => item.column === value)

    sort.value = selected ? firstSort(selected) : null
  },
})

const flip = (): void => {
  if (sort.value) {
    sort.value = { ...sort.value, direction: sort.value.direction === 'asc' ? 'desc' : 'asc' }
  }
}
</script>

<template>
  <div class="flex items-center gap-1">
    <USelect
      v-model="column"
      :items="items"
      icon="i-lucide-arrow-up-down"
      aria-label="Sort by"
      class="w-44"
    />

    <UButton
      v-if="sort"
      :icon="sort.direction === 'asc' ? 'i-lucide-arrow-up' : 'i-lucide-arrow-down'"
      :aria-label="sort.direction === 'asc' ? 'Sorted ascending, sort descending' : 'Sorted descending, sort ascending'"
      color="neutral"
      variant="outline"
      @click="flip"
    />
  </div>
</template>
