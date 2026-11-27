import PinController from '@/actions/Modules/Web/Users/Controllers/PinController'
import type { EnvironmentData, PinData, PinType } from '@/types'
import { router } from '@inertiajs/vue3'
import { toValue, type MaybeRefOrGetter } from 'vue'

type PinnedProps = { pinned?: PinData[] | PinData | null }

/** The id of a pin shown before the server has stored it. Stored pins start at 1. */
const pendingId = 0

/**
 * Pins things in an environment for the current user, such as an application to the overview, or unpins them.
 * Only the page's `pinned` prop (the pins on a list page, or the one pin on a detail page) changes,
 * right away, and then reloads with what the server stored.
 */
export function usePins(environment: MaybeRefOrGetter<EnvironmentData>) {
  const pin = (type: PinType, name: string): void => {
    const pending: PinData = { id: pendingId, type, name }

    router
      .optimistic<PinnedProps>((props) => ({
        pinned: Array.isArray(props.pinned)
          ? [...props.pinned, pending].sort((first, second) => first.name.localeCompare(second.name))
          : pending,
      }))
      .post(
        PinController.store.url(toValue(environment).id),
        { type, name },
        { preserveScroll: true, only: ['pinned'] },
      )
  }

  /** A pin the server hasn't stored yet has nothing to remove, so it stays until the pin request finishes. */
  const unpin = (pinned: PinData): void => {
    if (pinned.id === pendingId) {
      return
    }

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
