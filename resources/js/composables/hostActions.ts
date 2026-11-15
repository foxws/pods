import type { RequestPayload } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

type Method = 'post' | 'put' | 'delete'

/**
 * Sends a write to the host and keeps the page where it is. The server flashes the outcome as a toast.
 */
export function useHostAction() {
  const processing = ref(false)

  const send = (method: Method, url: string, data: RequestPayload = {}, onSuccess?: () => void): void => {
    router.visit(url, {
      method,
      data,
      preserveScroll: true,
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
