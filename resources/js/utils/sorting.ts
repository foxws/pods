export type SortDirection = 'asc' | 'desc'

export type TableSort = { column: string; direction: SortDirection }

export type SortValue = string | number | boolean | null | undefined

/**
 * A column a table can sort by: `column` is the column's id (its accessorKey), and `value` what it sorts on.
 */
export type SortableColumn<T> = {
  column: string
  label: string
  value: (row: T) => SortValue
  /** Sort the largest first on the first click, such as CPU, memory or size. */
  descendingFirst?: boolean
}

const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' })

const isMissing = (value: SortValue): value is null | undefined | '' =>
  value === null || value === undefined || value === ''

/**
 * Rows in the order of `sort`, keeping the order they came in for equal values. Rows without a value
 * go last either way, so "Memory, largest first" doesn't start with containers that report none.
 */
export function sortRows<T>(rows: T[], sort: TableSort | null, columns: SortableColumn<T>[]): T[] {
  const column = sort ? columns.find((item) => item.column === sort.column) : undefined

  if (!sort || !column) {
    return rows
  }

  const order = sort.direction === 'asc' ? 1 : -1

  return rows
    .map((row, index) => ({ row, index, value: column.value(row) }))
    .sort((a, b) => {
      if (isMissing(a.value) || isMissing(b.value)) {
        return Number(isMissing(a.value)) - Number(isMissing(b.value)) || a.index - b.index
      }

      return compareValues(a.value, b.value) * order || a.index - b.index
    })
    .map(({ row }) => row)
}

function compareValues(a: string | number | boolean, b: string | number | boolean): number {
  if (typeof a === 'number' && typeof b === 'number') {
    return a - b
  }

  return collator.compare(String(a), String(b))
}

/**
 * The sort a column starts with: largest first for the columns that ask for it, else ascending.
 */
export function firstSort<T>(column: SortableColumn<T>): TableSort {
  return { column: column.column, direction: column.descendingFirst ? 'desc' : 'asc' }
}

/**
 * A header click: the column's first direction, then the other one, then the order the rows came in.
 */
export function nextSort<T>(current: TableSort | null, column: SortableColumn<T>): TableSort | null {
  const first = firstSort(column)

  if (current?.column !== column.column) {
    return first
  }

  if (current.direction === first.direction) {
    return { column: column.column, direction: first.direction === 'asc' ? 'desc' : 'asc' }
  }

  return null
}

/**
 * A timestamp as milliseconds, so dates sort as times rather than as text.
 */
export function timeOf(value: string | null | undefined): number | null {
  const time = value ? Date.parse(value) : Number.NaN

  return Number.isNaN(time) ? null : time
}
