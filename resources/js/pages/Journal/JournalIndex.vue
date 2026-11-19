<script setup lang="ts">
import JournalController from '@/actions/Modules/Web/Journal/Controllers/JournalController'
import JournalLog from '@/components/Applications/JournalLog.vue'
import HostError from '@/components/Environments/HostError.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import { useHostReload } from '@/composables/polling'
import type { EnvironmentData, HostProperty, JournalEntryData } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import type { SelectItem, TabsItem } from '@nuxt/ui'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  since: '15m' | '1h' | 'today' | 'boot'
  entries?: HostProperty<JournalEntryData[]>
}>()

const list = computed(() => props.entries?.data ?? [])

const unitName = (unit: string): string => unit.replace(/\.service$/, '')

const units = computed<SelectItem[]>(() => [
  { label: `All units · ${new Set(list.value.map((entry) => entry.unit)).size}`, value: 'all' },
  ...[...new Set(list.value.map((entry) => entry.unit))].sort().map((unit) => ({ label: unitName(unit), value: unit })),
])

const unit = ref('all')
const level = ref<'all' | 'warnings' | 'errors'>('all')
const search = ref('')
const isFollowing = ref(true)

const levels = computed<TabsItem[]>(() => [
  { label: 'All', value: 'all' },
  { label: 'Warnings', value: 'warnings', badge: list.value.filter((entry) => entry.priority === 4).length },
  { label: 'Errors', value: 'errors', badge: list.value.filter((entry) => entry.priority <= 3).length },
])

const windows: TabsItem[] = [
  { label: '15 min', value: '15m' },
  { label: '1 h', value: '1h' },
  { label: 'Today', value: 'today' },
  { label: 'Boot', value: 'boot' },
]

const visible = computed(() => {
  const query = search.value.trim().toLowerCase()

  return list.value.filter(
    (entry) =>
      (unit.value === 'all' || entry.unit === unit.value) &&
      (level.value === 'all' || (level.value === 'errors' ? entry.priority <= 3 : entry.priority === 4)) &&
      (!query || entry.message.toLowerCase().includes(query)),
  )
})

const changeWindow = (since: string | number): void => {
  router.get(
    JournalController.url(props.environment.id, { query: { since: String(since) } }),
    {},
    { preserveState: true, replace: true },
  )
}

const { pause, resume } = useHostReload(['entries'], 5000)

const toggleFollowing = (): void => {
  isFollowing.value = !isFollowing.value

  if (isFollowing.value) {
    resume()

    return
  }

  pause()
}

const download = (): void => {
  const text = visible.value.map((entry) => `${entry.time} ${entry.unit} ${entry.message}`).join('\n')
  const url = URL.createObjectURL(new Blob([`${text}\n`], { type: 'text/plain' }))
  const link = document.createElement('a')

  link.href = url
  link.download = `journal-${props.environment.hostname ?? props.environment.host}.log`
  link.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <Head title="Journal" />

  <UDashboardPanel id="journal">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading title="Journal">
        <template #description>
          <code class="font-mono text-highlighted">journalctl</code> for Pods-managed units on
          <span class="font-medium text-highlighted">{{ environment.hostname ?? environment.host }}</span>.
        </template>

        <template #actions>
          <UButton
            label="Download"
            icon="i-lucide-download"
            color="neutral"
            variant="outline"
            :disabled="!visible.length"
            @click="download"
          />
          <UButton
            :label="isFollowing ? 'Following' : 'Paused'"
            :icon="isFollowing ? 'i-lucide-radio' : 'i-lucide-pause'"
            :color="isFollowing ? 'primary' : 'neutral'"
            :variant="isFollowing ? 'soft' : 'outline'"
            :aria-pressed="isFollowing"
            @click="toggleFollowing"
          />
        </template>
      </PageHeading>

      <div class="flex flex-wrap items-center gap-3">
        <USelect
          v-model="unit"
          :items="units"
          class="w-full sm:w-56"
          aria-label="Unit"
        />
        <UTabs
          v-model="level"
          :items="levels"
          :content="false"
          size="sm"
          color="neutral"
        />
        <UTabs
          :model-value="since"
          :items="windows"
          :content="false"
          size="sm"
          color="neutral"
          @update:model-value="changeWindow"
        />
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Filter messages"
          aria-label="Filter messages"
          class="w-full sm:ml-auto sm:w-64"
        />
      </div>

      <HostError
        v-if="entries?.error"
        :error="entries.error"
      />

      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <JournalLog
          v-if="visible.length"
          :entries="visible"
        />
        <p
          v-else-if="entries"
          class="p-6 text-center text-sm text-muted"
        >
          No journal entries match this filter.
        </p>
        <div
          v-else
          class="flex flex-col gap-2 p-4"
        >
          <USkeleton
            v-for="index in 8"
            :key="index"
            class="h-4"
          />
        </div>

        <template #footer>
          <p class="flex items-center gap-2 text-xs text-muted">
            <span
              class="size-1.5 rounded-full"
              :class="isFollowing ? 'bg-success' : 'bg-muted'"
              aria-hidden="true"
            />
            {{ isFollowing ? 'Streaming' : 'Paused' }} · {{ visible.length }} of {{ list.length }} lines
          </p>
        </template>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
