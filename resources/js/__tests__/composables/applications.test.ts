import { useHostPolling } from '@/composables/applications'
import { router } from '@inertiajs/vue3'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test'
import { nextTick, reactive, ref, toValue, type MaybeRefOrGetter } from 'vue'

const page = reactive({ props: { refresher: 10 as number | undefined } })

vi.mock('@inertiajs/vue3', () => ({
  router: {
    reload: vi.fn<(options: { only: string[]; onFinish: () => void }) => void>((options) => options.onFinish()),
  },
  usePage: () => page,
}))

// The tests run without a DOM, where VueUse never starts an interval, so they
// take the reload, the interval and the controls useHostReload hands it.
let poll:
  | {
      reload: () => void
      interval: MaybeRefOrGetter<number>
      pause: ReturnType<typeof vi.fn<() => void>>
      resume: ReturnType<typeof vi.fn<() => void>>
    }
  | undefined

vi.mock('@vueuse/core', () => ({
  useDocumentVisibility: () => ref('visible'),
  useIntervalFn: (reload: () => void, interval: MaybeRefOrGetter<number>) => {
    const pause = vi.fn<() => void>()
    const resume = vi.fn<() => void>()
    poll = { reload, interval, pause, resume }

    return { isActive: ref(true), pause, resume }
  },
}))

describe('useHostPolling', () => {
  beforeEach(() => {
    page.props.refresher = 10
  })

  afterEach(() => {
    vi.clearAllMocks()
    poll = undefined
  })

  it('reloads as often as the setting says while nothing is changing state, without the busy-only props', () => {
    page.props.refresher = 180

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
    expect(toValue(poll?.interval)).toBe(10_000)
    expect(router.reload).toHaveBeenLastCalledWith(expect.objectContaining({ only: ['application'] }))
  })

  it('follows a unit that changes state as often as a setting shorter than 5 seconds', () => {
    page.props.refresher = 3

    useHostPolling(['application'], () => true)

    expect(toValue(poll?.interval)).toBe(3_000)
  })

  it('stops reloading when the setting is off, but still follows a unit that changes state', async () => {
    page.props.refresher = 0
    const isBusy = ref(false)

    useHostPolling(['application'], isBusy)

    expect(poll?.pause).toHaveBeenCalledOnce()

    isBusy.value = true
    await nextTick()

    expect(poll?.resume).toHaveBeenCalledOnce()
    expect(toValue(poll?.interval)).toBe(5_000)
  })
})
