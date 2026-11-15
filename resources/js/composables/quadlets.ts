import QuadletCheckController from '@/actions/Modules/Web/Quadlets/Controllers/QuadletCheckController'
import { store } from '@/actions/Modules/Web/Quadlets/Controllers/QuadletController'
import type { EnvironmentData, HostProperty, QuadletCheckData } from '@/types'
import type { QuadletFile } from '@/utils/quadlet'
import { useForm, useHttp } from '@inertiajs/vue3'
import { watchDebounced } from '@vueuse/core'
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue'

export type SecretInput = { name: string; driver: 'file' | 'pass' | 'shell'; value: string }

/**
 * Installs Quadlet files on the host as one application, creating the secrets they need first.
 */
export function useQuadletInstall(environment: MaybeRefOrGetter<EnvironmentData>) {
  const form = useForm<{
    application: string
    files: QuadletFile[]
    secrets: SecretInput[]
    start: boolean
    replace: boolean
  }>({
    application: '',
    files: [],
    secrets: [],
    start: true,
    replace: false,
  })

  const install = (onSuccess?: () => void): void => {
    form.post(store.url(toValue(environment).id), {
      preserveScroll: true,
      onSuccess: () => onSuccess?.(),
      onFinish: () => {
        form.secrets = form.secrets.map((secret) => ({ ...secret, value: '' }))
      },
    })
  }

  return { form, install }
}

/**
 * Runs the files through podman quadlet --dryrun on the host while they are edited.
 */
export function useQuadletCheck(
  environment: MaybeRefOrGetter<EnvironmentData>,
  files: MaybeRefOrGetter<QuadletFile[]>,
) {
  const http = useHttp<{ files: QuadletFile[] }, HostProperty<QuadletCheckData>>({ files: [] })
  const result = ref<HostProperty<QuadletCheckData> | null>(null)

  const check = async (): Promise<void> => {
    http.files = toValue(files)

    if (!http.files.length) {
      result.value = null

      return
    }

    try {
      result.value = await http.post(QuadletCheckController.url(toValue(environment).id))
    } catch {
      result.value = null
    }
  }

  watchDebounced(() => JSON.stringify(toValue(files)), check, { debounce: 800, immediate: true })

  return { result, checking: computed(() => http.processing), check }
}
