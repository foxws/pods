import PinController from '@/actions/Modules/Web/Users/Controllers/PinController'
import type { EnvironmentData, PinData, PinType } from '@/types'
import { router } from '@inertiajs/vue3'
import { toValue, type MaybeRefOrGetter } from 'vue'

type PinnedProps = { pinned?: PinData[] | PinData | null }

/**
 * Pins things on a host for the current user, such as an application to the overview, or unpins them.
 * Only the page's `pinned` prop (the pins on a list page, or the one pin on a detail page) reloads.
 * An unpin shows right away; a new pin shows once the server gives it its id.
 */
export function usePins(environment: MaybeRefOrGetter<EnvironmentData>) {
  const pin = (type: PinType, name: string): void => {
    router.post(
      PinController.store.url(toValue(environment).id),
      { type, name },
      { preserveScroll: true, only: ['pinned'] },
    )
  }

  const unpin = (pinned: PinData): void => {
    router
      .optimistic<PinnedProps>((props) => ({
        pinned: Array.isArray(props.pinned) ? props.pinned.filter((item) => item.id !== pinned.id) : null,
      }))
      .delete(PinController.destroy.url([toValue(environment).id, pinned.id]), {
        preserveScroll: true,
        only: ['pinned'],
      })
  }

  return { pin, unpin }
}
