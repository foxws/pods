import ApplicationOperationController from '@/actions/Modules/Web/Applications/Controllers/ApplicationOperationController'
import type { ApplicationData, ApplicationOperation, EnvironmentData, HostProperty } from '@/types'
import { useHostReload } from '@/composables/polling'
import { router } from '@inertiajs/vue3'
import { computed, toValue, type MaybeRefOrGetter } from 'vue'

const pendingStatus: Record<ApplicationOperation, ApplicationData['status']> = {
  up: 'activating',
  down: 'deactivating',
  'force-stop': 'deactivating',
  restart: 'activating',
  redeploy: 'activating',
}

/**
 * Starts, stops and restarts applications. Listed applications switch to their pending status right away.
 */
export function useApplicationOperations(environment: MaybeRefOrGetter<EnvironmentData>) {
  const url = (name: string, operation: ApplicationOperation): string =>
    ApplicationOperationController.url([toValue(environment).id, name, operation])

  const run = (name: string, operation: ApplicationOperation): void => {
    router
      .optimistic<{ applications?: HostProperty<ApplicationData[]> }>(({ applications }) => {
        if (!applications) {
          return {}
        }

        return {
          applications: {
            error: applications.error,
            data: (applications.data ?? []).map((item) =>
              item.name === name ? { ...item, status: pendingStatus[operation] } : item,
            ),
          },
        }
      })
      .post(url(name, operation), {}, { preserveScroll: true })
  }

  return { run }
}

/**
 * Reloads host-backed props: every 5 seconds while a unit is changing state, every 30 seconds otherwise.
 */
export function useHostPolling(only: string[], isBusy: MaybeRefOrGetter<boolean>) {
  return useHostReload(
    only,
    computed(() => (toValue(isBusy) ? 5000 : 30000)),
  )
}
