<script setup lang="ts">
import { useNotifications } from '@/composables/notifications'
import type { NotificationData } from '@/types'
import { formatRelative } from '@/utils/format'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  notification: NotificationData
}>()

const { levelOf, setRead, remove } = useNotifications()

const isUnread = computed(() => !props.notification.read_at)
const level = computed(() => levelOf(props.notification))

/**
 * Following the link reads the notification too.
 */
const open = () => {
  if (isUnread.value) {
    setRead(props.notification, true)
  }
}
</script>

<template>
  <li
    class="group/row relative flex items-start gap-3 rounded-lg border p-3 transition-colors"
    :class="isUnread ? 'border-default bg-elevated/50' : 'border-transparent hover:bg-elevated/30'"
  >
    <UIcon
      :name="level.icon"
      class="mt-0.5 size-5 shrink-0"
      :class="level.color"
    />

    <div class="flex min-w-0 flex-1 flex-col gap-0.5 text-sm">
      <div class="flex flex-wrap items-center gap-2">
        <Link
          v-if="notification.url"
          :href="notification.url"
          class="font-medium after:absolute after:inset-0"
          :class="isUnread ? 'text-highlighted' : 'text-default'"
          @click="open"
        >
          {{ notification.title }}
        </Link>
        <span
          v-else
          class="font-medium"
          :class="isUnread ? 'text-highlighted' : 'text-default'"
        >
          {{ notification.title }}
        </span>

        <UBadge
          v-if="isUnread"
          label="New"
          color="primary"
          variant="subtle"
          size="sm"
        />
      </div>

      <p
        v-if="notification.message"
        class="break-words text-muted"
      >
        {{ notification.message }}
      </p>

      <p class="text-xs text-muted">
        <time :datetime="notification.created_at ?? undefined">{{ formatRelative(notification.created_at) }}</time>
        <template v-if="notification.environment"> · {{ notification.environment }}</template>
      </p>
    </div>

    <div class="relative z-10 flex shrink-0 gap-1">
      <UTooltip :text="isUnread ? 'Mark as read' : 'Mark as unread'">
        <UButton
          :icon="isUnread ? 'i-lucide-mail-open' : 'i-lucide-mail'"
          :aria-label="isUnread ? 'Mark as read' : 'Mark as unread'"
          color="neutral"
          variant="ghost"
          size="sm"
          @click="setRead(notification, isUnread)"
        />
      </UTooltip>

      <UTooltip text="Remove">
        <UButton
          icon="i-lucide-trash-2"
          aria-label="Remove notification"
          color="neutral"
          variant="ghost"
          size="sm"
          @click="remove(notification)"
        />
      </UTooltip>
    </div>
  </li>
</template>
