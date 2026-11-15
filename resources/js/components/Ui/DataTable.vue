<script setup lang="ts" generic="T extends object">
import type { TableColumn, TabsItem } from '@nuxt/ui'
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
  }>(),
  {
    filters: () => [],
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
const search = ref('')

const rows = computed(() => props.data ?? [])

const countOf = (item: DataTableFilter<T>): number =>
  item.match ? rows.value.filter(item.match).length : rows.value.length

const tabs = computed<TabsItem[]>(() =>
  props.filters.map((item) => ({ label: item.label, value: item.value, badge: countOf(item) })),
)

const visible = computed(() => {
  const active = props.filters.find((item) => item.value === filter.value)
  const query = search.value.trim().toLowerCase()

  return rows.value.filter(
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
      v-if="filters.length || searchText || $slots.toolbar"
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
