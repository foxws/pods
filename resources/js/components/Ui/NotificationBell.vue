<script setup lang="ts">
import { useNavigation } from '@/composables/navigation'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const { notificationsLink } = useNavigation()

const unread = computed(() => page.props.notifications ?? 0)
</script>

<template>
  <UChip
    :show="unread > 0"
    :text="unread > 99 ? '99+' : unread"
    color="error"
    size="3xl"
    inset
  >
    <UButton
      :to="notificationsLink.to"
      icon="i-lucide-bell"
      color="neutral"
      :variant="notificationsLink.active ? 'soft' : 'ghost'"
      size="lg"
      square
      :aria-label="unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'"
    />
  </UChip>
</template>
