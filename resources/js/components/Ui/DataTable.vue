<script setup lang="ts" generic="T extends object">
import SortableHeader from '@/components/Ui/SortableHeader.vue'
import TableSortControls from '@/components/Ui/TableSortControls.vue'
import { useTableSort } from '@/composables/tables'
import type { SortableColumn, TableSort } from '@/utils/sorting'
import type { TableColumn, TabsItem } from '@nuxt/ui'
import { usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

export type DataTableFilter<T> = {
  label: string
  value: string
  /** Rows the filter keeps. Leave it out to keep every row. */
  match?: (row: T) => boolean
}

const props = withDefaults(
  defineProps<{
    data: T[] | null | undefined
    columns: TableColumn<T>[]
    /** Shows skeleton rows while a deferred prop loads. */
    loading?: boolean
    filters?: DataTableFilter<T>[]
    /** The text a search matches against. Leave it out to hide the search field. */
    searchText?: (row: T) => string
    searchPlaceholder?: string
    empty?: string
    label: string
    rowKey?: (row: T) => string
    /** The columns a click on their header sorts by, matched by column id. */
    sortable?: SortableColumn<T>[]
    defaultSort?: TableSort | null
  }>(),
  {
    filters: () => [],
    sortable: () => [],
    defaultSort: null,
    searchPlaceholder: 'Filter',
    empty: 'Nothing matches this filter.',
  },
)

defineSlots<
  {
    /** A row on phones. Without it the table scrolls sideways. */
    item?: (props: { row: T }) => unknown
    toolbar?: () => unknown
    footer?: () => unknown
  } & Record<string, (props: any) => unknown>
>()

const filter = ref(props.filters[0]?.value ?? 'all')
const page = usePage()

/** The search palette links here with ?search= for resources without a page of their own. */
const search = ref(props.searchText ? (new URL(page.url, 'http://pods.local').searchParams.get('search') ?? '') : '')

const rows = computed(() => props.data ?? [])

const { sort, sorted, directionOf, toggle } = useTableSort(rows, () => props.sortable, props.defaultSort)

const countOf = (item: DataTableFilter<T>): number =>
  item.match ? rows.value.filter(item.match).length : rows.value.length

const tabs = computed<TabsItem[]>(() =>
  props.filters.map((item) => ({ label: item.label, value: item.value, badge: countOf(item) })),
)

const visible = computed(() => {
  const active = props.filters.find((item) => item.value === filter.value)
  const query = search.value.trim().toLowerCase()

  return sorted.value.filter(
    (row) =>
      (!active?.match || active.match(row)) &&
      (!query || !props.searchText || props.searchText(row).toLowerCase().includes(query)),
  )
})

const isLoading = computed(() => props.loading && !props.data)
</script>

<template>
  <div class="flex flex-col gap-3">
    <div
      v-if="filters.length || searchText || $slots.toolbar || (sortable.length && $slots.item)"
      class="flex flex-wrap items-center justify-between gap-3"
    >
      <UTabs
        v-if="filters.length"
        v-model="filter"
        :items="tabs"
        :content="false"
        size="sm"
        color="neutral"
        :ui="{ list: 'overflow-x-auto', trigger: 'shrink-0' }"
        class="max-w-full"
      />

      <div class="flex w-full items-center gap-2 sm:w-auto">
        <slot name="toolbar" />

        <TableSortControls
          v-if="sortable.length && $slots.item"
          v-model="sort"
          :columns="sortable"
          class="md:hidden"
        />

        <UInput
          v-if="searchText"
          v-model="search"
          icon="i-lucide-search"
          :placeholder="searchPlaceholder"
          :aria-label="searchPlaceholder"
          class="w-full sm:w-64"
        />
      </div>
    </div>

    <ul
      v-if="$slots.item"
      class="flex flex-col divide-y divide-default rounded-lg border border-default md:hidden"
      :aria-label="label"
    >
      <template v-if="isLoading">
        <li
          v-for="index in 4"
          :key="index"
          class="p-3"
        >
          <USkeleton class="h-10" />
        </li>
      </template>

      <li
        v-for="(row, index) in visible"
        v-else
        :key="rowKey ? rowKey(row) : index"
      >
        <slot
          name="item"
          :row="row"
        />
      </li>

      <li
        v-if="!isLoading && !visible.length"
        class="p-4 text-center text-sm text-muted"
      >
        {{ empty }}
      </li>
    </ul>

    <UTable
      :data="visible"
      :columns="columns"
      :loading="isLoading"
      :empty="empty"
      :aria-label="label"
      class="rounded-lg border border-default"
      :class="{ 'hidden md:block': $slots.item }"
    >
      <template
        v-for="column in sortable"
        :key="column.column"
        #[`${column.column}-header`]
      >
        <SortableHeader
          :label="column.label"
          :direction="directionOf(column.column)"
          @toggle="toggle(column)"
        />
      </template>

      <template
        v-for="(_, name) in $slots"
        :key="name"
        #[name]="slotProps"
      >
        <slot
          v-if="name !== 'item' && name !== 'toolbar' && name !== 'footer'"
          :name="name"
          v-bind="slotProps"
        />
      </template>
    </UTable>

    <slot name="footer" />
  </div>
</template>
