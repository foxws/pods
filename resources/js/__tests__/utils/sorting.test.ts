import { nextSort, sortRows, type SortableColumn } from '@/utils/sorting'
import { describe, expect, it } from 'vite-plus/test'

type Row = { name: string; memory: number | null }

const rows: Row[] = [
  { name: 'hello-10', memory: 300 },
  { name: 'nextcloud', memory: null },
  { name: 'hello-2', memory: 100 },
  { name: 'Immich', memory: 300 },
]

const name: SortableColumn<Row> = { column: 'name', label: 'Name', value: (row) => row.name }
const memory: SortableColumn<Row> = {
  column: 'memory',
  label: 'Memory',
  value: (row) => row.memory,
  descendingFirst: true,
}
const columns = [name, memory]

const names = (sorted: Row[]): string[] => sorted.map((row) => row.name)

describe('sortRows', () => {
  it('keeps the order the rows came in without a sort', () => {
    expect(sortRows(rows, null, columns)).toBe(rows)
  })

  it('sorts text naturally and without regard to case', () => {
    expect(names(sortRows(rows, { column: 'name', direction: 'asc' }, columns))).toEqual([
      'hello-2',
      'hello-10',
      'Immich',
      'nextcloud',
    ])
  })

  it('puts rows without a value last in both directions, and keeps ties in their order', () => {
    expect(names(sortRows(rows, { column: 'memory', direction: 'desc' }, columns))).toEqual([
      'hello-10',
      'Immich',
      'hello-2',
      'nextcloud',
    ])
    expect(names(sortRows(rows, { column: 'memory', direction: 'asc' }, columns))).toEqual([
      'hello-2',
      'hello-10',
      'Immich',
      'nextcloud',
    ])
  })
})

describe('nextSort', () => {
  it('goes through the first direction, the other one, and back to no sort', () => {
    const first = nextSort(null, name)
    const second = nextSort(first, name)

    expect(first).toEqual({ column: 'name', direction: 'asc' })
    expect(second).toEqual({ column: 'name', direction: 'desc' })
    expect(nextSort(second, name)).toBeNull()
  })

  it('starts with the largest first for columns that ask for it', () => {
    expect(nextSort({ column: 'name', direction: 'desc' }, memory)).toEqual({ column: 'memory', direction: 'desc' })
  })
})
