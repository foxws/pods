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
 * Reloads host-backed props: every 5 seconds while a unit is changing state, every 3 minutes otherwise.
 * The changes Pods makes reach the page at once as live props, so the slow poll only catches what
 * changed outside Pods, such as a unit that crashed. `whileBusy` names props only worth a reload
 * while a unit is changing state, such as its logs.
 */
export function useHostPolling(only: string[], isBusy: MaybeRefOrGetter<boolean>, whileBusy: string[] = []) {
  return useHostReload(
    () => (toValue(isBusy) ? [...only, ...whileBusy] : only),
    computed(() => (toValue(isBusy) ? 5000 : 180_000)),
  )
}
