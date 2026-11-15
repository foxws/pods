<script setup lang="ts">
import { show } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import HostError from '@/components/Environments/HostError.vue'
import SecretCreateModal from '@/components/Secrets/SecretCreateModal.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import type { ApplicationData, EnvironmentData, HostProperty, SecretData } from '@/types'
import { formatRelative } from '@/utils/format'
import { Head, Link } from '@inertiajs/vue3'
import type { TableColumn } from '@nuxt/ui'
import { onMounted, ref } from 'vue'

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
        :data="secrets?.data ?? []"
        :columns="columns"
        :loading="!secrets"
        empty="There are no secrets on this host."
        class="rounded-lg border border-default"
      >
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
      </UTable>

      <SecretCreateModal
        v-model:open="isCreating"
        :environment="environment"
        :applications="applications?.data ?? []"
        :prefill="prefill"
      />
    </template>
  </UDashboardPanel>
</template>
