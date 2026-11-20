import { nextSort, sortRows, type SortableColumn, type SortDirection, type TableSort } from '@/utils/sorting'
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue'

/**
 * The sort of a table, kept in the page, so a poll or a live update reloads the rows without losing it.
 * The table headers and the phone's sort controls share it, so both show the same order.
 */
export function useTableSort<T>(
  rows: MaybeRefOrGetter<T[]>,
  columns: MaybeRefOrGetter<SortableColumn<T>[]>,
  initial: TableSort | null = null,
) {
  const sort = ref<TableSort | null>(initial)

  const sorted = computed(() => sortRows(toValue(rows), sort.value, toValue(columns)))

  const directionOf = (column: string): SortDirection | null =>
    sort.value?.column === column ? sort.value.direction : null

  const toggle = (column: SortableColumn<T>): void => {
    sort.value = nextSort(sort.value, column)
  }

  return { sort, sorted, directionOf, toggle }
}
