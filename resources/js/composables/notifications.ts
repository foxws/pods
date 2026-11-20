import NotificationBulkController from '@/actions/Modules/Web/Notifications/Controllers/NotificationBulkController'
import { destroy, update } from '@/actions/Modules/Web/Notifications/Controllers/NotificationController'
import type { NotificationData, NotificationLevel } from '@/types'
import { router, usePage } from '@inertiajs/vue3'
import { useEchoNotification } from '@laravel/echo-vue'

const levels: Record<NotificationLevel, { icon: string; color: string }> = {
  success: { icon: 'i-lucide-circle-check', color: 'text-success' },
  error: { icon: 'i-lucide-circle-x', color: 'text-error' },
  warning: { icon: 'i-lucide-triangle-alert', color: 'text-warning' },
  info: { icon: 'i-lucide-bell', color: 'text-primary' },
}

/**
 * The props a change to the notifications reloads: the unread count, and the page's list (`messages`) when it's open.
 */
const reloadOptions = () => ({
  preserveScroll: true,
  only: ['notifications', 'messages'],
  reset: ['messages'],
})

/**
 * Refreshes the unread count, and the notifications page, whenever one of the user's notifications is broadcast.
 */
export function useNotificationUpdates(userId: string): void {
  const page = usePage()

  useEchoNotification(`users.${userId}`, () => {
    router.reload('messages' in page.props ? reloadOptions() : { only: ['notifications'] })
  })
}

export function useNotifications() {
  const levelOf = (notification: NotificationData) => levels[notification.level] ?? levels.info

  const setRead = (notification: NotificationData, read: boolean): void => {
    router.put(update.url(notification.id), { read }, reloadOptions())
  }

  const remove = (notification: NotificationData): void => {
    router.delete(destroy.url(notification.id), reloadOptions())
  }

  const markAllRead = (): void => {
    router.put(NotificationBulkController.update.url(), {}, reloadOptions())
  }

  const clearRead = (): void => {
    router.delete(NotificationBulkController.destroy.url(), reloadOptions())
  }

  return { levelOf, setRead, remove, markAllRead, clearRead }
}
