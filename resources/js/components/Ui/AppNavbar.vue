<script setup lang="ts">
import EnvironmentSwitcher from '@/components/Environments/EnvironmentSwitcher.vue'
import AppLogo from '@/components/Ui/AppLogo.vue'
import UserMenu from '@/components/Ui/UserMenu.vue'
import { useNavigation } from '@/composables/navigation'
import { Link } from '@inertiajs/vue3'
import type { BreadcrumbItem } from '@nuxt/ui'

defineProps<{
  /** Replaces the search field on desktop, e.g. on a detail page. */
  breadcrumb?: BreadcrumbItem[]
  /** Replaces the logo and environment switcher on phones with a back link. */
  back?: { label: string; to: string }
}>()

const { environment } = useNavigation()
</script>

<template>
  <UDashboardNavbar
    :toggle="false"
    :ui="{ root: 'h-14 gap-2 px-2 sm:px-4 lg:h-16 lg:gap-4 lg:px-8', left: 'flex-1 gap-2' }"
  >
    <template #left>
      <div class="flex min-w-0 flex-1 items-center gap-2 lg:hidden">
        <template v-if="back">
          <UButton
            :to="back.to"
            icon="i-lucide-arrow-left"
            color="neutral"
            variant="ghost"
            square
            size="lg"
            :aria-label="`Back to ${back.label.toLowerCase()}`"
          />
          <Link
            :href="back.to"
            class="truncate font-medium text-muted"
            >{{ back.label }}</Link
          >
        </template>

        <template v-else>
          <AppLogo
            compact
            class="px-1"
          />
          <EnvironmentSwitcher
            :current="environment"
            class="min-w-0 flex-1"
          />
        </template>
      </div>

      <div class="hidden min-w-0 flex-1 items-center lg:flex">
        <UBreadcrumb
          v-if="breadcrumb"
          :items="breadcrumb"
        />
        <UDashboardSearchButton
          v-else
          label="Search apps, containers, secrets"
          :kbds="['meta', 'k']"
          class="w-full max-w-md"
        />
      </div>
    </template>

    <template #right>
      <UDashboardSearchButton
        collapsed
        color="neutral"
        variant="ghost"
        size="lg"
        :kbds="[]"
        class="lg:hidden"
      />

      <span
        v-if="environment"
        class="hidden items-center gap-2 text-sm text-muted lg:flex"
      >
        <span
          class="size-2 rounded-full"
          :class="environment.last_seen_at ? 'bg-success' : 'bg-muted'"
          aria-hidden="true"
        />
        {{ environment.last_seen_at ? 'Connected' : 'Not verified' }}
      </span>

      <UserMenu class="hidden lg:inline-flex" />
    </template>
  </UDashboardNavbar>
</template>
