<script setup lang="ts">
import SaveButton from '@/components/Ui/SaveButton.vue'
import { show, update } from '@/actions/Modules/Web/Settings/Controllers/CatalogSettingsController'
import type { CatalogSettings } from '@/types'
import { useForm, useHttp } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'

// CatalogSettings::MaxRepositories
const maxRepositories = 10
// Not in the template: vue-tsc loses the loop's variables after a // in an attribute there.
const repositoryPlaceholder = 'https://github.com/example/quadlets'

const loaded = ref(false)

const http = useHttp<object, CatalogSettings>({})
const form = useForm<CatalogSettings>({ repositories: [] })

onMounted(async () => {
  const { repositories } = await http.get(show.url())

  form.defaults({ repositories })
  form.reset()
  loaded.value = true
})

const errorOf = (key: string): string | undefined => form.errors[key as keyof typeof form.errors]

const addRepository = (): void => {
  form.repositories.push({ repository: '', reference: 'main' })
}

const removeRepository = (index: number): void => {
  form.repositories.splice(index, 1)
  // Errors go by index, so they'd move to another row.
  form.clearErrors()
}

const onSubmit = (): void => {
  form.put(update.url(), {
    preserveScroll: true,
    onSuccess: () => form.defaults(),
  })
}
</script>

<template>
  <form @submit.prevent="onSubmit">
    <UPageCard
      title="Repositories"
      description="Every directory at a repository's root with a pods.yml is one application. The list is the same for all hosts, and removing a repository keeps what you installed from it."
      variant="naked"
      orientation="vertical"
      :ui="{ body: 'flex w-full flex-col gap-3' }"
    >
      <template #body>
        <USkeleton
          v-if="!loaded"
          class="h-40 w-full rounded-md"
        />

        <template v-else>
          <UAlert
            color="warning"
            variant="subtle"
            icon="i-lucide-shield-alert"
            title="Only add repositories you trust"
            description="Your hosts clone them, and what you install from them runs as your user there. A private repository needs an SSH URL and a key on each host."
          />

          <p
            v-if="!form.repositories.length"
            class="text-sm text-muted"
          >
            No repositories, so the catalog is empty. You can still install from a Git URL or your own files.
          </p>

          <div
            v-for="(source, index) in form.repositories"
            :key="index"
            class="flex flex-col gap-3 rounded-md border border-default p-3 sm:flex-row sm:items-start"
          >
            <UFormField
              label="Repository"
              :error="errorOf(`repositories.${index}.repository`)"
              class="min-w-0 flex-1"
            >
              <UInput
                v-model="source.repository"
                :model-modifiers="{ trim: true }"
                :placeholder="repositoryPlaceholder"
                class="w-full font-mono"
                spellcheck="false"
                required
              />
            </UFormField>

            <UFormField
              label="Branch or tag"
              :error="errorOf(`repositories.${index}.reference`)"
              class="sm:w-40"
            >
              <UInput
                v-model="source.reference"
                :model-modifiers="{ trim: true }"
                placeholder="main"
                class="w-full font-mono"
                spellcheck="false"
                required
              />
            </UFormField>

            <UButton
              color="neutral"
              variant="ghost"
              icon="i-lucide-trash-2"
              :aria-label="`Remove repository ${source.repository || index + 1}`"
              class="self-end sm:mt-6 sm:self-start"
              @click="removeRepository(index)"
            />
          </div>

          <p
            v-if="form.errors.repositories"
            class="text-sm text-error"
          >
            {{ form.errors.repositories }}
          </p>

          <UButton
            color="neutral"
            variant="outline"
            icon="i-lucide-plus"
            label="Add repository"
            class="self-start"
            :disabled="form.repositories.length >= maxRepositories"
            @click="addRepository"
          />
        </template>
      </template>

      <template #footer>
        <SaveButton
          v-if="loaded"
          :form="form"
          label="Save catalog"
          :disabled="!form.isDirty"
        />
      </template>
    </UPageCard>
  </form>
</template>
