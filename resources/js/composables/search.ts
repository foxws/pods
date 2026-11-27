import EnvironmentSearchController from '@/actions/Modules/Web/Environments/Controllers/EnvironmentSearchController'
import type { EnvironmentData, HostProperty, SearchResultData } from '@/types'
import { useHttp } from '@inertiajs/vue3'
import type { CommandPaletteGroup } from '@nuxt/ui'
import { computed, ref, toValue, watch, type MaybeRefOrGetter, type Ref } from 'vue'

const groups: { id: string; label: string; icon: string }[] = [
  { id: 'applications', label: 'Applications', icon: 'i-lucide-layers' },
  { id: 'containers', label: 'Containers', icon: 'i-lucide-box' },
  { id: 'pods', label: 'Pods', icon: 'i-lucide-boxes' },
  { id: 'images', label: 'Images', icon: 'i-lucide-disc-3' },
  { id: 'volumes', label: 'Volumes', icon: 'i-lucide-hard-drive' },
  { id: 'networks', label: 'Networks', icon: 'i-lucide-network' },
  { id: 'secrets', label: 'Secrets', icon: 'i-lucide-key-round' },
]

/**
 * Loads what is on the host each time the search palette opens, and groups it for the palette.
 */
export function useHostSearch(environment: MaybeRefOrGetter<EnvironmentData | null>, open: Ref<boolean>) {
  const http = useHttp<object, HostProperty<SearchResultData[]>>({})
  const results = ref<SearchResultData[]>([])
  const error = ref<string | null>(null)

  const load = async (): Promise<void> => {
    const current = toValue(environment)

    if (!current) {
      results.value = []

      return
    }

    try {
      const response = await http.get(EnvironmentSearchController.url(current.id))

      results.value = response.data ?? []
      error.value = response.error
    } catch {
      error.value = 'Pods could not search this environment.'
    }
  }

  watch(open, (isOpen) => isOpen && load())
  watch(
    () => toValue(environment)?.id,
    () => {
      results.value = []
      error.value = null
    },
  )

  const searchGroups = computed<CommandPaletteGroup[]>(() =>
    groups.map(({ id, label, icon }) => ({
      id,
      label,
      items: results.value
        .filter((result) => result.group === id)
        .map((result) => ({ label: result.label, suffix: result.description ?? undefined, icon, to: result.url })),
    })),
  )

  return { searchGroups, loading: computed(() => http.processing), error }
}
