<script setup lang="ts">
import { show as applicationShow } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import { destroy } from '@/actions/Modules/Web/Images/Controllers/ImageController'
import ImagePruneController from '@/actions/Modules/Web/Images/Controllers/ImagePruneController'
import ImageUpdateController from '@/actions/Modules/Web/Images/Controllers/ImageUpdateController'
import HostError from '@/components/Environments/HostError.vue'
import ImagePullModal from '@/components/Images/ImagePullModal.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import ConfirmModal from '@/components/Ui/ConfirmModal.vue'
import DataTable, { type DataTableFilter } from '@/components/Ui/DataTable.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import RowActions from '@/components/Ui/RowActions.vue'
import StatCard from '@/components/Ui/StatCard.vue'
import StatGrid from '@/components/Ui/StatGrid.vue'
import StateBadge from '@/components/Ui/StateBadge.vue'
import { useHostAction } from '@/composables/hostActions'
import type { EnvironmentData, HostProperty, ImageData } from '@/types'
import { pluralize } from '@/utils/application'
import { formatBytes, formatRelative } from '@/utils/format'
import { Head, Link } from '@inertiajs/vue3'
import type { BadgeProps, DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { computed, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  images?: HostProperty<ImageData[]>
}>()

const list = computed(() => props.images?.data ?? [])

const isUnused = (image: ImageData): boolean => !image.dangling && image.containers === 0
const unused = computed(() => list.value.filter((image) => image.dangling || isUnused(image)))
const updates = computed(() => list.value.filter((image) => image.update))

const sizeOf = (images: ImageData[]): number => images.reduce((total, image) => total + (image.size ?? 0), 0)

const imageName = (image: ImageData): string => image.repository?.split('/').pop() ?? '<none>'
const registry = (image: ImageData): string =>
  image.repository ? image.repository.split('/').slice(0, -1).join('/') : 'untagged layer'

const status = (image: ImageData): { label: string; color: BadgeProps['color'] } => {
  if (image.dangling) {
    return { label: 'Dangling', color: 'warning' }
  }

  if (image.update) {
    return { label: `Update ${image.update}`, color: 'info' }
  }

  return image.containers ? { label: 'In use', color: 'success' } : { label: 'Unused', color: 'neutral' }
}

const filters: DataTableFilter<ImageData>[] = [
  { label: 'All', value: 'all' },
  { label: 'In use', value: 'used', match: (image) => image.containers > 0 },
  { label: 'Unused', value: 'unused', match: isUnused },
  { label: 'Dangling', value: 'dangling', match: (image) => image.dangling },
]

const columns: TableColumn<ImageData>[] = [
  { accessorKey: 'repository', header: 'Image' },
  { accessorKey: 'tag', header: 'Tag' },
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'size', header: 'Size' },
  { accessorKey: 'used_by', header: 'Used by' },
  { accessorKey: 'created_at', header: 'Created' },
  { id: 'status', header: 'Status' },
  { accessorKey: 'auto_update', header: 'Auto-update' },
  { id: 'actions', header: '' },
]

const isPulling = ref(false)
const isPruning = ref(false)
const removing = ref<ImageData | null>(null)
const isRemoving = computed({
  get: () => removing.value !== null,
  set: (open) => {
    if (!open) {
      removing.value = null
    }
  },
})

const { send, processing } = useHostAction()

const actions = (image: ImageData): DropdownMenuItem[] => [
  {
    label: 'Remove',
    icon: 'i-lucide-trash-2',
    color: 'error',
    disabled: image.containers > 0,
    onSelect: () => {
      removing.value = image
    },
  },
]

const searchText = (image: ImageData): string => [image.repository, image.tag, image.id, ...image.used_by].join(' ')
</script>

<template>
  <Head title="Images" />

  <UDashboardPanel id="images">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading title="Images">
        <template #description>
          Images in the {{ environment.scope ?? 'local' }} store for
          <span class="font-medium text-highlighted">{{ environment.user }}</span>. Quadlets with
          <code class="font-mono text-highlighted">AutoUpdate=registry</code> are pulled by the auto-update timer.
        </template>

        <template #actions>
          <UButton
            label="Prune unused"
            icon="i-lucide-brush-cleaning"
            color="neutral"
            variant="outline"
            :disabled="!unused.length"
            @click="isPruning = true"
          />
          <UButton
            label="Check for updates"
            icon="i-lucide-refresh-cw"
            color="neutral"
            variant="outline"
            :loading="processing"
            @click="send('post', ImageUpdateController.url(environment.id))"
          />
          <UButton
            label="Pull image"
            icon="i-lucide-download"
            @click="isPulling = true"
          />
        </template>
      </PageHeading>

      <StatGrid
        v-if="!images?.error"
        :loading="!images"
      >
        <StatCard
          label="Images"
          :value="list.length"
          :hint="`${list.filter((image) => image.containers > 0).length} in use`"
        />
        <StatCard
          label="Disk"
          :value="formatBytes(sizeOf(list))"
          hint="image store"
        />
        <StatCard
          label="Reclaimable"
          :value="formatBytes(sizeOf(unused))"
          :hint="pluralize(unused.length, 'image')"
        />
        <StatCard
          label="Updates"
          :value="updates.length"
          :hint="
            updates.map((image) => `${imageName(image)} ${image.tag} → ${image.update}`).join(', ') || 'all current'
          "
          :color="updates.length ? 'primary' : undefined"
        />
      </StatGrid>

      <HostError
        v-if="images?.error"
        :error="images.error"
      />

      <DataTable
        :data="images?.data"
        :columns="columns"
        :loading="!images"
        :filters="filters"
        :search-text="searchText"
        :row-key="(image) => image.id"
        search-placeholder="Filter images"
        empty="No images match this filter."
        label="Images"
      >
        <template #item="{ row }">
          <div class="flex min-h-16 items-center gap-3 px-3 py-2.5">
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="truncate font-medium text-highlighted">{{ imageName(row) }}:{{ row.tag ?? '<none>' }}</span>
              <span class="truncate text-xs text-muted"
                >{{ formatBytes(row.size) }} · {{ row.used_by.join(', ') || 'unused' }}</span
              >
            </span>
            <StateBadge v-bind="status(row)" />
          </div>
        </template>

        <template #repository-cell="{ row }">
          <span class="flex flex-col">
            <span class="font-medium text-highlighted">{{ imageName(row.original) }}</span>
            <span class="font-mono text-xs text-muted">{{ registry(row.original) }}</span>
          </span>
        </template>

        <template #tag-cell="{ row }">
          <span class="font-mono text-xs">
            {{ row.original.tag ?? '<none>' }}
            <span
              v-if="row.original.update"
              class="text-primary"
              >→ {{ row.original.update }}</span
            >
          </span>
        </template>

        <template #id-cell="{ row }">
          <span class="font-mono text-xs text-muted">{{ row.original.id.slice(0, 12) }}</span>
        </template>

        <template #size-cell="{ row }">
          <span class="text-muted">{{ formatBytes(row.original.size) }}</span>
        </template>

        <template #used_by-cell="{ row }">
          <span
            v-if="row.original.used_by.length"
            class="flex flex-wrap gap-x-2"
          >
            <Link
              v-for="application in row.original.used_by"
              :key="application"
              :href="applicationShow.url([environment.id, application])"
              class="text-primary"
              >{{ application }}</Link
            >
          </span>
          <span
            v-else
            class="text-muted"
            >–</span
          >
        </template>

        <template #created_at-cell="{ row }">
          <span class="text-muted">{{ formatRelative(row.original.created_at) }}</span>
        </template>

        <template #status-cell="{ row }">
          <StateBadge v-bind="status(row.original)" />
        </template>

        <template #auto_update-cell="{ row }">
          <span class="text-muted">{{ row.original.auto_update ?? 'off' }}</span>
        </template>

        <template #actions-cell="{ row }">
          <RowActions
            :items="actions(row.original)"
            :label="`Actions for ${imageName(row.original)}`"
          />
        </template>
      </DataTable>

      <ImagePullModal
        v-model:open="isPulling"
        :environment="environment"
      />

      <ConfirmModal
        v-model:open="isPruning"
        title="Prune unused images"
        description="Removes images that no container uses."
        :confirm-label="`Prune ${pluralize(unused.length, 'image')}`"
        :processing="processing"
        @confirm="(close) => send('post', ImagePruneController.url(environment.id), {}, close)"
      >
        <ul class="flex flex-col divide-y divide-default rounded-lg border border-default text-sm">
          <li
            v-for="image in unused"
            :key="image.id"
            class="flex items-center justify-between gap-3 px-3 py-2"
          >
            <span class="flex min-w-0 flex-col">
              <span class="truncate font-mono text-highlighted"
                >{{ image.repository ?? '<none>' }}:{{ image.tag ?? image.id.slice(0, 8) }}</span
              >
              <span class="text-xs text-muted"
                >{{ image.dangling ? 'dangling' : 'unused' }} · {{ formatRelative(image.created_at) }}</span
              >
            </span>
            <span class="shrink-0 text-muted">{{ formatBytes(image.size) }}</span>
          </li>
        </ul>
        <p class="flex justify-between text-sm">
          <span class="text-muted">Space freed</span>
          <span class="font-medium text-highlighted">{{ formatBytes(sizeOf(unused)) }}</span>
        </p>
      </ConfirmModal>

      <ConfirmModal
        v-model:open="isRemoving"
        :title="`Remove ${removing ? imageName(removing) : 'image'}?`"
        description="Podman deletes the image from the store. Pull it again to get it back."
        confirm-label="Remove image"
        :processing="processing"
        @confirm="(close) => removing && send('delete', destroy.url([environment.id, removing.id]), {}, close)"
      />
    </template>
  </UDashboardPanel>
</template>
