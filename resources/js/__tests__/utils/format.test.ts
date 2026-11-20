import { formatPercent, formatRate, percentOf } from '@/utils/format'
import { expect, test } from 'vite-plus/test'

test('rounds the cpu share podman reports', () => {
  expect(formatPercent(3.3117374108025945)).toBe('3.3%')
  expect(formatPercent(0.026469223036862357)).toBe('0.0%')
  expect(formatPercent(42.7)).toBe('43%')
  expect(formatPercent(null)).toBe('–')
})

test('gives a whole percentage of a total, and 0 without one', () => {
  expect(percentOf(512, 2048)).toBe(25)
  expect(percentOf(1, 3)).toBe(33)
  expect(percentOf(5, 0)).toBe(0)
})

test('formats a rate per second, or a dash without one', () => {
  expect(formatRate(null)).toBe('–')
  expect(formatRate(0)).toMatch(/\/s$/)
})
