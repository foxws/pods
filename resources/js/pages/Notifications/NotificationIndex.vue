<script setup lang="ts">
import NotificationItem from '@/components/Notifications/NotificationItem.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import { useNavigation } from '@/composables/navigation'
import { useNotifications } from '@/composables/notifications'
import type { NotificationData } from '@/types'
import { Head, InfiniteScroll, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

type Filter = 'all' | 'unread'

const props = defineProps<{
  filter: Filter
  messages: { data: NotificationData[] }
}>()

const page = usePage()
const { notificationsLink } = useNavigation()
const { markAllRead, clearRead } = useNotifications()

const unread = computed(() => page.props.notifications ?? 0)
const hasRead = computed(() => props.messages.data.some((notification) => notification.read_at))

const filters: { label: string; value: Filter }[] = [
  { label: 'All', value: 'all' },
  { label: 'Unread', value: 'unread' },
]

const applyFilter = (filter: Filter): void => {
  router.get(notificationsLink.value.to, filter === 'unread' ? { filter } : {}, {
    preserveState: true,
    preserveScroll: true,
    only: ['messages', 'filter'],
    reset: ['messages'],
  })
}

const isToday = (notification: NotificationData): boolean =>
  notification.created_at !== null &&
  new Date(notification.created_at.replace(' ', 'T')).toDateString() === new Date().toDateString()

const sections = computed(() =>
  [
    { id: 'today', title: 'Today', items: props.messages.data.filter(isToday) },
    { id: 'earlier', title: 'Earlier', items: props.messages.data.filter((item) => !isToday(item)) },
  ].filter((section) => section.items.length),
)
</script>

<template>
  <Head title="Notifications" />

  <UDashboardPanel id="notifications">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <PageHeading
          title="Notifications"
          :description="unread > 0 ? `${unread} unread` : 'You\'re all caught up.'"
        >
          <template #actions>
            <UButton
              v-if="unread > 0"
              label="Mark all read"
              icon="i-lucide-check-check"
              color="neutral"
              variant="outline"
              @click="markAllRead"
            />
            <UButton
              v-if="hasRead"
              label="Clear read"
              icon="i-lucide-trash-2"
              color="neutral"
              variant="outline"
              @click="clearRead"
            />
          </template>
        </PageHeading>

        <div
          class="flex gap-2"
          role="group"
          aria-label="Show"
        >
          <UButton
            v-for="option in filters"
            :key="option.value"
            :label="option.value === 'unread' && unread > 0 ? `${option.label} (${unread})` : option.label"
            :aria-pressed="filter === option.value"
            :color="filter === option.value ? 'primary' : 'neutral'"
            :variant="filter === option.value ? 'soft' : 'ghost'"
            size="sm"
            @click="applyFilter(option.value)"
          />
        </div>

        <UEmpty
          v-if="!sections.length"
          icon="i-lucide-bell-off"
          :title="filter === 'unread' ? 'No unread notifications' : 'No notifications'"
          description="Finished exports and other work that runs in the background show up here."
          variant="naked"
          class="py-16"
        />

        <InfiniteScroll
          v-else
          data="messages"
          items-element="#notification-sections"
          :buffer="200"
        >
          <div
            id="notification-sections"
            class="flex flex-col gap-6"
          >
            <section
              v-for="section in sections"
              :key="section.id"
              :aria-labelledby="`notifications-${section.id}`"
              class="flex flex-col gap-2"
            >
              <h2
                :id="`notifications-${section.id}`"
                class="text-xs font-medium text-muted uppercase"
              >
                {{ section.title }}
              </h2>

              <ul class="flex flex-col gap-1">
                <NotificationItem
                  v-for="notification in section.items"
                  :key="notification.id"
                  :notification="notification"
                />
              </ul>
            </section>
          </div>
        </InfiniteScroll>
      </div>
    </template>
  </UDashboardPanel>
</template>
