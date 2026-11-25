import type { OptionItem } from '@/types'

/**
 * The label of a value among options the backend shares, such as the labels of an enum, or the
 * fallback while they haven't loaded.
 */
export function optionLabel(
  options: OptionItem[] | undefined,
  value: OptionItem['value'],
  fallback: string = String(value),
): string {
  return options?.find((option) => option.value === value)?.label ?? fallback
}
