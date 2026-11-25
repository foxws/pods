import { optionLabel } from '@/utils/options'
import { expect, test } from 'vite-plus/test'

const options = [
  { label: 'Starting', value: 'activating' },
  { label: 'Off', value: 0 },
]

test('finds the label of a value', () => {
  expect(optionLabel(options, 'activating')).toBe('Starting')
  expect(optionLabel(options, 0)).toBe('Off')
})

test('falls back to the value, or the given fallback, while the options are missing', () => {
  expect(optionLabel(undefined, 'activating')).toBe('activating')
  expect(optionLabel(options, 'failed', 'Unknown')).toBe('Unknown')
})
