<script setup lang="ts">
import type { NavigationLink } from '@/composables/navigation'
import { Link } from '@inertiajs/vue3'

defineProps<{
  links: NavigationLink[]
}>()

const emit = defineEmits<{
  more: []
}>()
</script>

<template>
  <nav
    aria-label="Main"
    class="grid shrink-0 auto-cols-fr grid-flow-col border-t border-default bg-default pb-[env(safe-area-inset-bottom)]"
  >
    <Link
      v-for="link in links"
      :key="link.to"
      :href="link.to"
      :aria-current="link.active ? 'page' : undefined"
      class="flex h-16 flex-col items-center justify-center gap-0.5 text-[11px] font-medium"
      :class="link.active ? 'text-highlighted' : 'text-muted'"
    >
      <UIcon
        :name="link.icon"
        class="size-5"
        :class="link.active ? 'text-primary' : undefined"
      />
      {{ link.shortLabel }}
    </Link>

    <button
      type="button"
      class="flex h-16 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-muted"
      @click="emit('more')"
    >
      <UIcon
        name="i-lucide-menu"
        class="size-5"
      />
      More
    </button>
  </nav>
</template>
