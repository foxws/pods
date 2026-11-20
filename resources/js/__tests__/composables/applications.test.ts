import { useHostPolling } from '@/composables/applications'
import { router } from '@inertiajs/vue3'
import { afterEach, describe, expect, it, vi } from 'vite-plus/test'
import { ref, toValue, type MaybeRefOrGetter } from 'vue'

vi.mock('@inertiajs/vue3', () => ({
  router: {
    reload: vi.fn<(options: { only: string[]; onFinish: () => void }) => void>((options) => options.onFinish()),
  },
}))

// The tests run without a DOM, where VueUse never starts an interval, so they
// take the reload and the interval useHostReload hands it.
let poll: { reload: () => void; interval: MaybeRefOrGetter<number> } | undefined

vi.mock('@vueuse/core', () => ({
  useDocumentVisibility: () => ref('visible'),
  useIntervalFn: (reload: () => void, interval: MaybeRefOrGetter<number>) => {
    poll = { reload, interval }

    return { isActive: ref(true), pause: vi.fn<() => void>(), resume: vi.fn<() => void>() }
  },
}))

describe('useHostPolling', () => {
  afterEach(() => {
    vi.clearAllMocks()
    poll = undefined
  })

  it('reloads every 3 minutes while nothing is changing state, without the busy-only props', () => {
    useHostPolling(['application'], () => false, ['logs'])

    poll?.reload()

    expect(toValue(poll?.interval)).toBe(180_000)
    expect(router.reload).toHaveBeenCalledExactlyOnceWith(expect.objectContaining({ only: ['application'] }))
  })

  it('reloads every 5 seconds with the busy-only props while a unit changes state', () => {
    const isBusy = ref(true)

    useHostPolling(['application'], isBusy, ['logs'])

    poll?.reload()
    expect(toValue(poll?.interval)).toBe(5_000)
    expect(router.reload).toHaveBeenLastCalledWith(expect.objectContaining({ only: ['application', 'logs'] }))

    isBusy.value = false
    poll?.reload()
    expect(toValue(poll?.interval)).toBe(180_000)
    expect(router.reload).toHaveBeenLastCalledWith(expect.objectContaining({ only: ['application'] }))
  })
})
