<script setup lang="ts">
import { show } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import HostError from '@/components/Environments/HostError.vue'
import SecretCreateModal from '@/components/Secrets/SecretCreateModal.vue'
import SecretDetailModal from '@/components/Secrets/SecretDetailModal.vue'
import RowActions from '@/components/Ui/RowActions.vue'
import SortableHeader from '@/components/Ui/SortableHeader.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import type { ApplicationData, EnvironmentData, HostProperty, SecretData } from '@/types'
import { useTableSort } from '@/composables/tables'
import { formatRelative } from '@/utils/format'
import { timeOf, type SortableColumn } from '@/utils/sorting'
import { Head, Link } from '@inertiajs/vue3'
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { computed, onMounted, ref } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  secrets?: HostProperty<SecretData[]>
  applications?: HostProperty<ApplicationData[]>
  prefill: { name: string | null; application: string | null }
}>()

const isCreating = ref(false)

onMounted(() => {
  isCreating.value = props.prefill.name !== null
})

const columns: TableColumn<SecretData>[] = [
  { accessorKey: 'name', header: 'Name' },
  { accessorKey: 'driver', header: 'Driver' },
  { accessorKey: 'used_by', header: 'Used by' },
  { accessorKey: 'updated_at', header: 'Updated' },
  { id: 'actions', header: '' },
]

const sortable: SortableColumn<SecretData>[] = [
  { column: 'name', label: 'Name', value: (row) => row.name },
  { column: 'driver', label: 'Driver', value: (row) => row.driver },
  { column: 'used_by', label: 'Used by', value: (row) => row.used_by.length, descendingFirst: true },
  { column: 'updated_at', label: 'Updated', value: (row) => timeOf(row.updated_at), descendingFirst: true },
]

const { sorted, directionOf, toggle } = useTableSort(() => props.secrets?.data ?? [], sortable)

const viewing = ref<SecretData | null>(null)
const isViewing = computed({
  get: () => viewing.value !== null,
  set: (open) => {
    if (!open) {
      viewing.value = null
    }
  },
})

const createMissing = (secret: SecretData): void => {
  prefillName.value = secret.name
  isCreating.value = true
}

const prefillName = ref<string | null>(null)
const createPrefill = computed(() => ({
  name: prefillName.value ?? props.prefill.name,
  application: props.prefill.application,
}))

const actions = (secret: SecretData): DropdownMenuItem[] =>
  secret.missing
    ? [{ label: 'Create secret', icon: 'i-lucide-plus', onSelect: () => createMissing(secret) }]
    : [
        { label: 'Replace value', icon: 'i-lucide-refresh-cw', onSelect: () => (viewing.value = secret) },
        { label: 'Delete', icon: 'i-lucide-trash-2', color: 'error', onSelect: () => (viewing.value = secret) },
      ]
</script>

<template>
  <Head title="Secrets" />

  <UDashboardPanel id="secrets">
    <template #header>
      <AppNavbar />
    </template>

    <template #body>
      <PageHeading title="Secrets">
        <template #description>
          Podman secrets your Quadlets reference with
          <code class="font-mono text-highlighted">Secret=</code>. Values are never shown after creation.
        </template>

        <template #actions>
          <UButton
            icon="i-lucide-plus"
            label="Create secret"
            @click="isCreating = true"
          />
        </template>
      </PageHeading>

      <HostError
        v-if="secrets?.error"
        :error="secrets.error"
      />

      <UTable
        :data="sorted"
        :columns="columns"
        :loading="!secrets"
        empty="There are no secrets on this host."
        class="rounded-lg border border-default"
      >
        <template
          v-for="column in sortable"
          :key="column.column"
          #[`${column.column}-header`]
        >
          <SortableHeader
            :label="column.label"
            :direction="directionOf(column.column)"
            @toggle="toggle(column)"
          />
        </template>

        <template #name-cell="{ row }">
          <span class="flex items-center gap-2 font-mono text-highlighted">
            <UIcon
              :name="row.original.missing ? 'i-lucide-triangle-alert' : 'i-lucide-key-round'"
              :class="row.original.missing ? 'text-error' : 'text-muted'"
              class="size-4"
            />
            {{ row.original.name }}
          </span>
        </template>

        <template #driver-cell="{ row }">
          <UBadge
            v-if="row.original.missing"
            label="Missing"
            color="error"
            variant="subtle"
          />
          <UBadge
            v-else
            :label="row.original.driver ?? 'file'"
            color="neutral"
            variant="outline"
          />
        </template>

        <template #used_by-cell="{ row }">
          <span
            v-if="!row.original.used_by.length"
            class="text-muted"
            >Unused</span
          >
          <span
            v-else
            class="flex flex-wrap gap-x-2"
          >
            <Link
              v-for="application in row.original.used_by"
              :key="application"
              :href="show.url([environment.id, application])"
              class="text-primary"
              >{{ application }}</Link
            >
          </span>
        </template>

        <template #updated_at-cell="{ row }">
          <span class="text-muted">{{ row.original.missing ? '–' : formatRelative(row.original.updated_at) }}</span>
        </template>

        <template #actions-cell="{ row }">
          <RowActions
            :items="actions(row.original)"
            :label="`Actions for ${row.original.name}`"
          />
        </template>
      </UTable>

      <SecretCreateModal
        v-model:open="isCreating"
        :environment="environment"
        :applications="applications?.data ?? []"
        :prefill="createPrefill"
      />

      <SecretDetailModal
        v-model:open="isViewing"
        :environment="environment"
        :secret="viewing"
      />
    </template>
  </UDashboardPanel>
</template>
