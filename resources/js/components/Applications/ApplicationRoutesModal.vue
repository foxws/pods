<script setup lang="ts">
import { update } from '@/actions/Modules/Web/Applications/Controllers/ApplicationRoutesController'
import FormModal from '@/components/Ui/FormModal.vue'
import type { EnvironmentData, RouteSuggestionData } from '@/types'
import { suggestedHost } from '@/utils/routes'
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  application: string
  routes: RouteSuggestionData[]
  /** The proxy's wildcard domain, which hostnames stay one label under. */
  wildcard: string | null
  /** The proxy defaults' domain, which other hostnames are suggested under. */
  domain: string | null
}>()

type Row = {
  role: string
  description: string | null
  host: string
  upstream: string
  main: boolean
  viaMain: boolean
  fromManifest: boolean
}

const form = useForm<{ rows: Row[] }>({ rows: [] })

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }

  form.defaults({
    rows: props.routes.map((route) => ({
      role: route.role,
      description: route.description,
      host: route.host ?? '',
      upstream: route.upstream,
      main: route.main,
      viaMain: route.via === 'main',
      fromManifest: route.from_manifest,
    })),
  })
  form.reset()
  form.clearErrors()
})

const addRow = (): void => {
  const role = form.rows.some((row) => row.role === 'app') ? `route-${form.rows.length + 1}` : 'app'

  form.rows.push({
    role,
    description: null,
    host: '',
    upstream: '',
    main: form.rows.length === 0,
    viaMain: false,
    fromManifest: false,
  })
}

const removeRow = (index: number): void => {
  form.rows.splice(index, 1)
}

const makeMain = (index: number): void => {
  form.rows.forEach((row, rowIndex) => {
    row.main = rowIndex === index
    row.viaMain = row.main ? false : row.viaMain
  })
}

/**
 * Only rows with a hostname are sent, so an error's index is the row's place among those.
 */
const sent = (): Row[] => form.rows.filter((row) => row.host.trim() !== '')

const errorOf = (row: Row, field: 'host' | 'upstream' | 'role'): string | undefined => {
  const index = sent().indexOf(row)

  return index === -1 ? undefined : form.errors[`routes.${index}.${field}` as keyof typeof form.errors]
}

const onSubmit = (close: () => void): void => {
  form
    .transform(() => ({
      routes: sent().map((row) => ({
        role: row.role,
        host: row.host.trim(),
        upstream: row.upstream.trim(),
        main: row.main,
        via: !row.main && row.viaMain ? 'main' : null,
      })),
    }))
    .put(update.url([props.environment.id, props.application]), {
      preserveScroll: true,
      onSuccess: () => close(),
    })
}
</script>

<template>
  <FormModal
    v-model:open="open"
    :title="`Routes of ${application}`"
    description="The Pods proxy sends each hostname to an upstream of the application. Leave a hostname empty for no route. Saving reloads the proxy, or restarts it when it joins a new network."
    submit-label="Save routes"
    :processing="form.processing"
    :ui="{ content: 'sm:max-w-2xl' }"
    @submit="onSubmit"
  >
    <template #body>
      <div class="flex flex-col gap-4">
        <div
          v-for="(row, index) in form.rows"
          :key="index"
          class="flex flex-col gap-2 rounded-md border border-default p-3"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-2">
              <span class="font-mono text-sm font-medium text-highlighted">{{ row.role }}</span>
              <UBadge
                v-if="row.main"
                label="Main"
                color="primary"
                variant="subtle"
                size="sm"
              />
              <span
                v-if="row.description"
                class="truncate text-sm text-muted"
                >{{ row.description }}</span
              >
            </div>
            <div class="flex shrink-0 gap-1">
              <UButton
                v-if="!row.main"
                label="Make main"
                color="neutral"
                variant="ghost"
                size="xs"
                @click="makeMain(index)"
              />
              <UButton
                v-if="!row.fromManifest"
                icon="i-lucide-x"
                color="neutral"
                variant="ghost"
                size="xs"
                aria-label="Remove route"
                @click="removeRow(index)"
              />
            </div>
          </div>

          <div class="grid gap-2 sm:grid-cols-2">
            <UFormField
              label="Hostname"
              :error="errorOf(row, 'host') ?? errorOf(row, 'role')"
            >
              <UInput
                v-model="row.host"
                :placeholder="suggestedHost(application, row.role, row.main, wildcard, domain)"
                class="w-full font-mono"
              >
                <template
                  v-if="!row.host"
                  #trailing
                >
                  <UButton
                    label="Suggest"
                    color="neutral"
                    variant="link"
                    size="xs"
                    @click="row.host = suggestedHost(application, row.role, row.main, wildcard, domain)"
                  />
                </template>
              </UInput>
            </UFormField>
            <UFormField
              label="Upstream"
              :description="
                row.fromManifest
                  ? 'From pods.yml'
                  : 'A container and its port, or host.containers.internal or an IPv4 address of the host'
              "
              :error="errorOf(row, 'upstream')"
            >
              <UInput
                v-model="row.upstream"
                :disabled="row.fromManifest"
                placeholder="systemd-app:8000"
                class="w-full font-mono"
              />
            </UFormField>
          </div>

          <UCheckbox
            v-if="!row.main"
            v-model="row.viaMain"
            :disabled="row.fromManifest"
            label="Through the main route"
            description="For an application that routes its own hostnames. It wakes a sleeping application too."
          />
        </div>

        <UButton
          label="Add a route"
          icon="i-lucide-plus"
          color="neutral"
          variant="outline"
          size="sm"
          class="self-start"
          @click="addRow"
        />
      </div>
    </template>
  </FormModal>
</template>
