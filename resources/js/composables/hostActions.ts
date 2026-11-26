import type { RequestPayload, VisitOptions } from '@inertiajs/core'
import { router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

type Method = 'post' | 'put' | 'delete'

type LiveVisitOptions = Pick<VisitOptions, 'preserveScroll' | 'preserveState' | 'only'>

/**
 * Options for a write that comes back to the page it was made on. The page keeps its state and
 * reloads only its live props (the host reads a write can change, such as the application's status)
 * and `also` (props from the database, such as volume exports), instead of every prop. A full
 * reload would drop the deferred props back to their skeletons and read the logs and updates again.
 * A page without live props reloads as a whole, and a write that redirects to another page loads it
 * in full, since the server only answers a partial reload for the page that asked.
 */
export function useLiveVisit() {
  const page = usePage()

  return (also: string[] = []): LiveVisitOptions => {
    const live = Object.keys(page.liveProps ?? {}).filter((prop) => page.props[prop] !== undefined)
    const only = [...new Set([...live, ...also])]

    return { preserveScroll: true, preserveState: true, ...(only.length > 0 ? { only } : {}) }
  }
}

/**
 * Sends a write to the host and keeps the page where it is, reloading only what the write can change
 * (see `useLiveVisit()`), and `also`. The server flashes the outcome as a toast.
 */
export function useHostAction() {
  const processing = ref(false)
  const liveVisit = useLiveVisit()

  const send = (
    method: Method,
    url: string,
    data: RequestPayload = {},
    onSuccess?: () => void,
    also: string[] = [],
  ): void => {
    router.visit(url, {
      method,
      data,
      ...liveVisit(also),
      onStart: () => {
        processing.value = true
      },
      onSuccess: () => onSuccess?.(),
      onFinish: () => {
        processing.value = false
      },
    })
  }

  return { processing, send }
}
