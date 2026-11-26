import QuadletCheckController from '@/actions/Modules/Web/Quadlets/Controllers/QuadletCheckController'
import { store } from '@/actions/Modules/Web/Quadlets/Controllers/QuadletController'
import type { EnvironmentData, HostProperty, QuadletCheckData } from '@/types'
import type { QuadletFile } from '@/utils/quadlet'
import { useLiveVisit } from '@/composables/hostActions'
import { useForm, useHttp } from '@inertiajs/vue3'
import { watchDebounced } from '@vueuse/core'
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue'

export type SecretInput = { name: string; driver: 'file' | 'pass' | 'shell'; value: string }

/**
 * A secret Pods fills in on the server from a key of an entered .env secret. It carries no value.
 */
export type DerivedSecretInput = { name: string; from: string; key: string }

/**
 * A hostname for the Pods proxy to send to an upstream of the application once it's installed.
 */
export type RouteInput = { role: string; host: string; upstream: string; main: boolean; via: string | null }

/**
 * Where fetched files came from, with the pods.yml, so Pods can keep it with the installed application.
 */
export type InstallSourceInput = {
  repository: string
  reference: string
  path: string
  commit: string | null
  manifest: string | null
  manifest_path: string | null
}

/**
 * Installs Quadlet files on the host as one application, creating the secrets they need first.
 */
export function useQuadletInstall(environment: MaybeRefOrGetter<EnvironmentData>) {
  const form = useForm<{
    application: string
    files: QuadletFile[]
    secrets: SecretInput[]
    derived_secrets: DerivedSecretInput[]
    start: boolean
    replace: boolean
    source: InstallSourceInput | null
    edit: boolean
    restart: boolean
    routes: RouteInput[]
  }>({
    application: '',
    files: [],
    secrets: [],
    derived_secrets: [],
    start: true,
    replace: false,
    source: null,
    edit: false,
    restart: false,
    routes: [],
  })

  const liveVisit = useLiveVisit()

  const install = (onSuccess?: () => void): void => {
    form.post(store.url(toValue(environment).id), {
      ...liveVisit(),
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
