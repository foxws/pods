import ApplicationOperationController from '@/actions/Modules/Web/Applications/Controllers/ApplicationOperationController'
import type { ApplicationData, ApplicationOperation, EnvironmentData, HostProperty } from '@/types'
import { useHostReload } from '@/composables/polling'
import { router, usePage } from '@inertiajs/vue3'
import { computed, toValue, watch, type MaybeRefOrGetter } from 'vue'

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
 * Reloads host-backed props: every 5 seconds (or sooner, when the setting is shorter) while a unit is
 * changing state, and otherwise as often as Settings, General says (`hostRefresh`, in seconds; 0 stops it). The changes Pods makes reach the page
 * at once as live props, so the slower reload only catches what changed outside Pods, such as a unit
 * that crashed, or the host's CPU and memory. `whileBusy` names props only worth a reload while a unit
 * is changing state, such as its logs.
 */
export function useHostPolling(only: string[], isBusy: MaybeRefOrGetter<boolean>, whileBusy: string[] = []) {
  const page = usePage()
  const refreshSeconds = computed(() => Number(page.props.hostRefresh ?? 10))
  const isOff = computed(() => !toValue(isBusy) && refreshSeconds.value <= 0)
  const busyInterval = computed(() => (refreshSeconds.value > 0 ? Math.min(5000, refreshSeconds.value * 1000) : 5000))

  const controls = useHostReload(
    () => (toValue(isBusy) ? [...only, ...whileBusy] : only),
    computed(() => (toValue(isBusy) || isOff.value ? busyInterval.value : refreshSeconds.value * 1000)),
  )

  watch(
    isOff,
    (off) => {
      if (off) {
        controls.pause()

        return
      }

      controls.resume()
    },
    { immediate: true },
  )

  return controls
}
