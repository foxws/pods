import { update } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentLpodController'
import { store as verify } from '@/actions/Modules/Web/Environments/Controllers/EnvironmentVerificationController'
import { useHostAction } from '@/composables/hostActions'
import type { EnvironmentData } from '@/types'
import { toValue, type MaybeRefOrGetter } from 'vue'

/**
 * Checks the lpod of an environment again, or updates it to the version Pods needs. Both verify the
 * host, which stores its facts, so the pages reload them (as does the environment switcher).
 */
export function useLpodActions(environment: MaybeRefOrGetter<EnvironmentData>) {
  const verifiedProps = ['environment', 'lpod', 'environments']

  const { send: sendUpdate, processing: isUpdating } = useHostAction()
  const { send: sendCheck, processing: isChecking } = useHostAction()

  const updateLpod = (): void => sendUpdate('put', update.url(toValue(environment).id), {}, undefined, verifiedProps)

  const checkAgain = (): void => sendCheck('post', verify.url(toValue(environment).id), {}, undefined, verifiedProps)

  return { updateLpod, checkAgain, isUpdating, isChecking }
}
