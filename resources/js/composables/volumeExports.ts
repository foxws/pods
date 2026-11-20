import type { VolumeExportData } from '@/types'
import { useEchoNotification } from '@laravel/echo-vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from '@nuxt/ui/composables'
import { formatBytes } from '@/utils/format'

type VolumeExportFinished = {
  type: string
  environment: string
  export: VolumeExportData
}

/**
 * Tells the user when one of their volume exports is ready, or why it failed, on whichever page
 * they're on, and refreshes the Volumes page's list of exports.
 */
export function useVolumeExportNotifications(userId: string): void {
  const toast = useToast()
  const page = usePage()

  useEchoNotification<VolumeExportFinished>(`users.${userId}`, (notification) => {
    if (notification.type !== 'volume-export.finished') {
      return
    }

    const { export: finished, environment } = notification

    if (finished.status === 'completed') {
      toast.add({
        title: `${finished.volume} is exported`,
        description: `The archive (${formatBytes(finished.size)}) from ${environment} is ready to download.`,
        color: 'success',
        icon: 'i-lucide-circle-check',
        actions: finished.download_url
          ? [{ label: 'Download', icon: 'i-lucide-download', to: finished.download_url, external: true }]
          : [],
      })
    } else {
      toast.add({
        title: `${finished.volume} wasn't exported`,
        description: finished.error ?? `The export from ${environment} failed.`,
        color: 'error',
        icon: 'i-lucide-circle-x',
      })
    }

    if ('exports' in page.props) {
      router.reload({ only: ['exports'] })
    }
  })
}
