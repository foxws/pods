import { router } from '@inertiajs/vue3'
import { useDocumentVisibility, useIntervalFn, type UseIntervalFnOptions } from '@vueuse/core'
import { watch, type MaybeRefOrGetter } from 'vue'

/**
 * Reloads host-backed props on an interval, but only while the page is visible: a hidden tab
 * doesn't ask the host for anything, and reloads once when it's shown again. A reload never
 * starts while the last one is still waiting on the host, so a slow host doesn't pile them up.
 */
export function useHostReload(only: string[], interval: MaybeRefOrGetter<number>, options?: UseIntervalFnOptions) {
  const visibility = useDocumentVisibility()
  let isReloading = false

  const reload = (): void => {
    if (isReloading || visibility.value === 'hidden') {
      return
    }

    isReloading = true

    router.reload({
      only,
      onFinish: () => {
        isReloading = false
      },
    })
  }

  const controls = useIntervalFn(reload, interval, options)

  watch(visibility, (current, previous) => {
    if (current === 'visible' && previous === 'hidden' && controls.isActive.value) {
      reload()
    }
  })

  return controls
}
