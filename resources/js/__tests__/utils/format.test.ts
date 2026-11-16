import { formatPercent } from '@/utils/format'
import { expect, test } from 'vite-plus/test'

test('rounds the cpu share podman reports', () => {
  expect(formatPercent(3.3117374108025945)).toBe('3.3%')
  expect(formatPercent(0.026469223036862357)).toBe('0.0%')
  expect(formatPercent(42.7)).toBe('43%')
  expect(formatPercent(null)).toBe('–')
})
