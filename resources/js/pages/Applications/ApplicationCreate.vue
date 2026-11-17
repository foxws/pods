<script setup lang="ts">
import { index as applicationsIndex } from '@/actions/Modules/Web/Applications/Controllers/ApplicationController'
import QuadletFetchController from '@/actions/Modules/Web/Quadlets/Controllers/QuadletFetchController'
import HostError from '@/components/Environments/HostError.vue'
import QuadletCheckStatus from '@/components/Quadlets/QuadletCheckStatus.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import { useQuadletCheck, useQuadletInstall, type DerivedSecretInput, type SecretInput } from '@/composables/quadlets'
import type {
  CatalogApplicationData,
  CatalogData,
  EnvironmentData,
  HostProperty,
  ManifestData,
  ManifestSourceData,
  QuadletSourceData,
  SecretData,
} from '@/types'
import { pluralize } from '@/utils/application'
import {
  describeQuadlet,
  isQuadletFile,
  manifestFile,
  secretsIn,
  selectedByDefault,
  type QuadletFile,
} from '@/utils/quadlet'
import { Head, useHttp } from '@inertiajs/vue3'
import type { BreadcrumbItem, TabsItem } from '@nuxt/ui'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  catalog?: HostProperty<CatalogData>
  secrets?: HostProperty<SecretData[]>
}>()

type Source = 'catalog' | 'git' | 'upload' | 'write'

const source = ref<Source>('catalog')
const sources: TabsItem[] = [
  { label: 'Catalog', value: 'catalog', icon: 'i-lucide-layout-grid' },
  { label: 'Git repository', value: 'git', icon: 'i-lucide-git-branch' },
  { label: 'Upload files', value: 'upload', icon: 'i-lucide-upload' },
  { label: 'Write quadlet', value: 'write', icon: 'i-lucide-pencil' },
]

const available = ref<QuadletFile[]>([])
const selected = ref<string[]>([])
const commit = ref<string | null>(null)
const manifest = ref<ManifestData | null>(null)
const manifestError = ref<string | null>(null)

const fetcher = useHttp<{ repository: string; reference: string; path: string }, HostProperty<QuadletSourceData>>({
  repository: '',
  reference: 'main',
  path: '',
})
const fetchError = ref<string | null>(null)

const git = reactive({ repository: '', reference: 'main', path: '' })
const gitErrors = computed<Partial<Record<'repository' | 'reference' | 'path', string>>>(() => fetcher.errors)
const isFetching = computed(() => fetcher.processing)

const fetchRepository = async (): Promise<void> => {
  fetchError.value = null
  fetcher.repository = git.repository
  fetcher.reference = git.reference
  fetcher.path = git.path

  try {
    const response = await fetcher.post(QuadletFetchController.url(props.environment.id))

    if (response.error || !response.data) {
      fetchError.value = response.error ?? 'lpod returned no files.'

      return
    }

    commit.value = response.data.commit
    manifest.value = response.data.manifest
    manifestError.value = response.data.manifest_error
    chosenSource.value = activeSource.value?.name
    showFiles(response.data.files)

    for (const secret of manifest.value?.secrets ?? []) {
      if (secret.generate && !values[secret.name]) {
        generate(secret.name)
      }
    }

    if (!application.name && manifest.value?.name) {
      application.name = manifest.value.name
    }

    if (!application.name && git.path) {
      application.name = git.path.replace(/\/+$/, '').split('/').pop() ?? ''
    }
  } catch {
    fetchError.value = fetcher.hasErrors ? null : 'The repository could not be fetched.'
  }
}

const activeSource = computed<ManifestSourceData | null>(
  () => manifest.value?.sources.find((choice) => choice.path === git.path.replace(/^\/+|\/+$/g, '')) ?? null,
)

const chosenSource = ref<string | undefined>(undefined)

const sourceChoices = computed(() =>
  (manifest.value?.sources ?? []).map((choice) => ({
    label: choice.name,
    description: choice.description ?? choice.path,
    value: choice.name,
  })),
)

const chooseSource = (name: string | undefined): void => {
  const choice = manifest.value?.sources.find((item) => item.name === name)

  if (!choice || choice.path === activeSource.value?.path) {
    return
  }

  git.path = choice.path
  fetchRepository()
}

const catalogEntry = ref<CatalogApplicationData | null>(null)
const catalogSearch = ref('')

const catalogResults = computed(() => {
  const query = catalogSearch.value.trim().toLowerCase()

  return (props.catalog?.data?.applications ?? []).filter((entry) =>
    [entry.name, entry.manifest.label, entry.manifest.description, entry.manifest.category, entry.manifest.author].some(
      (value) => value?.toLowerCase().includes(query),
    ),
  )
})

/**
 * A catalog application is a directory with its own pods.yml. Pods fetches its first source
 * straight away (or the directory itself when it has none); the source picker then switches
 * between them, such as production and development.
 */
const chooseCatalogEntry = (entry: CatalogApplicationData): void => {
  catalogEntry.value = entry
  git.repository = entry.repository
  git.reference = entry.reference
  git.path = entry.manifest.sources[0]?.path ?? entry.path
  application.name = entry.name
  fetchRepository()
}

const leaveCatalogEntry = (): void => {
  catalogEntry.value = null
  available.value = []
  selected.value = []
  commit.value = null
  manifest.value = null
  manifestError.value = null
  fetchError.value = null
}

const showFiles = (files: QuadletFile[]): void => {
  available.value = files
  selected.value = selectedByDefault(files)
}

const uploaded = ref<File[] | null>(null)

watch(uploaded, async (list) => {
  showFiles(await Promise.all((list ?? []).map(async (file) => ({ name: file.name, content: await file.text() }))))
  commit.value = null
})

const written = reactive({ name: '', content: '[Container]\nImage=\n\n[Install]\nWantedBy=default.target\n' })

watch(source, () => {
  catalogEntry.value = null
  available.value = []
  selected.value = []
  commit.value = null
  manifest.value = null
  manifestError.value = null
})

const files = computed<QuadletFile[]>(() => {
  if (source.value === 'write') {
    return written.name ? [{ name: written.name, content: written.content }] : []
  }

  return available.value.filter((file) => selected.value.includes(file.name))
})

const skipped = computed(() => available.value.filter((file) => !isQuadletFile(file.name)))

const toggle = (name: string, isSelected: boolean | 'indeterminate'): void => {
  selected.value =
    isSelected === true ? [...new Set([...selected.value, name])] : selected.value.filter((item) => item !== name)
}

const existing = computed(
  () => new Map((props.secrets?.data ?? []).filter((secret) => !secret.missing).map((secret) => [secret.name, secret])),
)

const referenced = computed(() => {
  const usage = new Map<string, string[]>()

  for (const file of files.value) {
    for (const name of secretsIn(file.content)) {
      usage.set(name, [...(usage.get(name) ?? []), file.name])
    }
  }

  return [...usage.entries()].map(([name, usedIn]) => ({
    name,
    usedIn,
    existing: existing.value.get(name) ?? null,
    hint: manifest.value?.secrets.find((secret) => secret.name === name) ?? null,
  }))
})

const missing = computed(() => referenced.value.filter((secret) => !secret.existing))

const values = reactive<Record<string, string>>({})

const generate = (name: string): void => {
  const bytes = crypto.getRandomValues(new Uint8Array(24))

  values[name] = btoa(String.fromCharCode(...bytes)).replace(/[+/=]/g, '')
}

/**
 * Secrets the manifest fills in from a key of a .env secret that's entered here, such as DB_PASSWORD
 * from the app's .env. Pods reads the key on the server when installing, the way Laravel reads .env.
 * Entering a value yourself replaces it.
 */
const isFilledIn = (secret: (typeof missing.value)[number]): boolean =>
  !!secret.hint?.from &&
  !!secret.hint.key &&
  !values[secret.name] &&
  missing.value.some((other) => other.name === secret.hint?.from)

const application = reactive({ name: '', start: true, replace: false })

const unfilled = computed(() => missing.value.filter((secret) => !values[secret.name] && !isFilledIn(secret)))

const { result, checking } = useQuadletCheck(() => props.environment, files)
const { form, install } = useQuadletInstall(() => props.environment)

const submit = (): void => {
  form.application = application.name
  form.files = files.value
  form.start = application.start
  form.replace = application.replace
  form.secrets = missing.value
    .filter((secret) => !isFilledIn(secret))
    .map<SecretInput>((secret) => ({ name: secret.name, driver: 'file', value: values[secret.name] ?? '' }))
  form.derived_secrets = missing.value.filter(isFilledIn).map<DerivedSecretInput>((secret) => ({
    name: secret.name,
    from: secret.hint?.from ?? '',
    key: secret.hint?.key ?? '',
  }))
  install(() => {
    for (const key of Object.keys(values)) {
      values[key] = ''
    }
  })
}

const counts = computed(() => {
  const ofKind = (kind: string): number => files.value.filter((file) => file.name.endsWith(`.${kind}`)).length

  return {
    pods: ofKind('pod'),
    containers: ofKind('container'),
    volumes: ofKind('volume'),
    networks: ofKind('network'),
    kube: ofKind('kube'),
  }
})

const unitsSummary = computed(() =>
  [
    counts.value.pods ? pluralize(counts.value.pods, 'pod') : null,
    counts.value.kube ? pluralize(counts.value.kube, 'kube') : null,
    pluralize(counts.value.containers, 'container'),
    counts.value.volumes ? pluralize(counts.value.volumes, 'volume') : null,
    counts.value.networks ? pluralize(counts.value.networks, 'network') : null,
  ]
    .filter(Boolean)
    .join(' · '),
)

const blocker = computed(() => {
  if (!files.value.length) {
    return 'Choose at least one Quadlet file'
  }

  if (!application.name) {
    return 'Name the application'
  }

  if (unfilled.value.length) {
    return `Fill in ${pluralize(unfilled.value.length, 'more secret')} to continue`
  }

  return result.value?.data?.valid === false ? 'Fix the errors the dry run found' : null
})

const quadletDir = computed(() =>
  props.environment.scope === 'rootful' ? '/etc/containers/systemd' : '~/.config/containers/systemd',
)

const breadcrumb = computed<BreadcrumbItem[]>(() => [
  { label: 'Applications', to: applicationsIndex.url(props.environment.id) },
  { label: 'Install application' },
])

const serverErrors = computed(() =>
  Object.entries(form.errors)
    .filter(([key]) => key !== 'application')
    .map(([, message]) => message),
)
</script>

<template>
  <Head title="Install application" />

  <UDashboardPanel id="application-create">
    <template #header>
      <AppNavbar
        :breadcrumb="breadcrumb"
        :back="{ label: 'Applications', to: applicationsIndex.url(environment.id) }"
      />
    </template>

    <template #body>
      <PageHeading title="Install application">
        <template #description>
          Install Quadlet files with <code class="font-mono text-highlighted">podman quadlet install</code>. Pods checks
          the files and the secrets they need before anything runs.
        </template>
      </PageHeading>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
        <div class="flex min-w-0 flex-col gap-4">
          <UCard>
            <template #header>
              <h2 class="flex items-center gap-2 font-semibold text-highlighted">
                <UBadge
                  label="1"
                  color="neutral"
                  variant="soft"
                />
                Source
              </h2>
            </template>

            <div class="flex flex-col gap-4">
              <UTabs
                v-model="source"
                :items="sources"
                :content="false"
                size="sm"
                color="neutral"
                :ui="{ list: 'overflow-x-auto', trigger: 'shrink-0' }"
              />

              <div
                v-if="source === 'catalog' && !catalogEntry"
                class="flex flex-col gap-3"
              >
                <UInput
                  v-model="catalogSearch"
                  icon="i-lucide-search"
                  placeholder="Search the catalog"
                  aria-label="Search the catalog"
                  class="w-full sm:w-80"
                />
                <HostError
                  v-if="catalog?.error"
                  :error="catalog.error"
                />
                <UAlert
                  v-if="catalog?.data?.errors.length"
                  color="warning"
                  variant="subtle"
                  icon="i-lucide-file-warning"
                  title="Some applications were left out, because Pods can't read their pods.yml"
                  :description="catalog.data.errors.join(' · ')"
                />
                <div
                  v-if="!catalog"
                  class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                  <USkeleton
                    v-for="index in 6"
                    :key="index"
                    class="h-20 w-full"
                  />
                </div>
                <ul
                  v-else-if="catalogResults.length"
                  class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                  <li
                    v-for="entry in catalogResults"
                    :key="entry.name"
                  >
                    <button
                      type="button"
                      class="flex h-full w-full items-start gap-3 rounded-md border border-default p-3 text-left transition hover:border-accented hover:bg-elevated/50 focus-visible:outline-2 focus-visible:outline-primary"
                      @click="chooseCatalogEntry(entry)"
                    >
                      <UIcon
                        :name="entry.manifest.icon ?? 'i-lucide-package'"
                        class="mt-0.5 size-5 shrink-0 text-primary"
                      />
                      <span class="flex min-w-0 flex-col gap-0.5">
                        <span class="font-medium text-highlighted">{{ entry.manifest.label ?? entry.name }}</span>
                        <span
                          v-if="entry.manifest.description"
                          class="text-sm text-muted"
                          >{{ entry.manifest.description }}</span
                        >
                        <span class="text-xs text-dimmed">{{
                          [entry.manifest.category, entry.manifest.author ? `by ${entry.manifest.author}` : null]
                            .filter(Boolean)
                            .join(' · ')
                        }}</span>
                      </span>
                    </button>
                  </li>
                </ul>
                <p
                  v-else-if="catalog.data"
                  class="text-sm text-muted"
                >
                  {{
                    catalogSearch
                      ? `Nothing in the catalog matches “${catalogSearch}”.`
                      : 'The catalog has no applications.'
                  }}
                </p>
              </div>

              <div
                v-if="source === 'catalog' && catalogEntry"
                class="flex flex-col gap-3 rounded-md border border-default p-4"
              >
                <div class="flex items-start gap-3">
                  <UIcon
                    :name="catalogEntry.manifest.icon ?? 'i-lucide-package'"
                    class="mt-0.5 size-6 shrink-0 text-primary"
                  />
                  <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <p class="font-semibold text-highlighted">{{ catalogEntry.manifest.label ?? catalogEntry.name }}</p>
                    <p
                      v-if="catalogEntry.manifest.description"
                      class="text-sm text-muted"
                    >
                      {{ catalogEntry.manifest.description }}
                    </p>
                  </div>
                  <UButton
                    label="Choose another"
                    color="neutral"
                    variant="outline"
                    size="sm"
                    :disabled="isFetching"
                    @click="leaveCatalogEntry"
                  />
                </div>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                  <template v-if="catalogEntry.manifest.author">
                    <dt class="text-muted">Author</dt>
                    <dd>{{ catalogEntry.manifest.author }}</dd>
                  </template>
                  <template
                    v-for="link in [
                      { label: 'Website', url: catalogEntry.manifest.website },
                      { label: 'Source code', url: catalogEntry.manifest.repository },
                    ].filter((item) => item.url)"
                    :key="link.label"
                  >
                    <dt class="text-muted">{{ link.label }}</dt>
                    <dd class="min-w-0 truncate">
                      <ULink
                        :to="link.url ?? undefined"
                        target="_blank"
                        class="hover:underline"
                        >{{ link.url }}</ULink
                      >
                    </dd>
                  </template>
                  <dt class="text-muted">Quadlets</dt>
                  <dd class="min-w-0 truncate font-mono text-xs leading-5">
                    <ULink
                      :to="catalogEntry.repository"
                      target="_blank"
                      class="hover:underline"
                      >{{ catalogEntry.repository }}</ULink
                    >
                    · {{ catalogEntry.reference }} · {{ catalogEntry.path }}/
                  </dd>
                </dl>
                <UAlert
                  v-if="catalogEntry.manifest.notes"
                  color="info"
                  variant="subtle"
                  icon="i-lucide-info"
                  :description="catalogEntry.manifest.notes"
                />
                <p
                  v-if="isFetching"
                  class="flex items-center gap-2 text-sm text-muted"
                >
                  <UIcon
                    name="i-lucide-loader-circle"
                    class="size-4 animate-spin"
                  />
                  Fetching the files on {{ environment.hostname ?? environment.host }}…
                </p>
              </div>

              <div
                v-if="source === 'git'"
                class="grid gap-4 sm:grid-cols-2"
                @keydown.enter.prevent="fetchRepository"
              >
                <UFormField
                  label="Repository URL"
                  :error="gitErrors.repository"
                  class="sm:col-span-2"
                >
                  <UInput
                    v-model="git.repository"
                    placeholder="https://codeberg.org/foxws/quadlets.git"
                    class="w-full font-mono"
                  />
                </UFormField>
                <UFormField
                  label="Branch or tag"
                  :error="gitErrors.reference"
                >
                  <UInput
                    v-model="git.reference"
                    class="w-full font-mono"
                  />
                </UFormField>
                <UFormField
                  label="Directory"
                  hint="Optional"
                  :error="gitErrors.path"
                >
                  <UInput
                    v-model="git.path"
                    placeholder="nextcloud/"
                    class="w-full font-mono"
                  />
                </UFormField>
                <UButton
                  label="Fetch repository"
                  icon="i-lucide-git-pull-request-arrow"
                  size="lg"
                  block
                  :loading="isFetching"
                  class="sm:col-span-2"
                  @click="fetchRepository"
                />
              </div>

              <UFileUpload
                v-if="source === 'upload'"
                multiple
                label="Drop Quadlet files here"
                description=".container, .pod, .volume, .network, .kube and .env files"
                accept=".container,.pod,.volume,.network,.kube,.image,.build,.env,.yaml,.yml"
                class="min-h-32 w-full"
                v-model="uploaded"
              />

              <div
                v-if="source === 'write'"
                class="flex flex-col gap-3"
              >
                <UFormField
                  label="File name"
                  description="Ends with .container, .pod, .volume, .network or .kube."
                >
                  <UInput
                    v-model="written.name"
                    :model-modifiers="{ trim: true }"
                    placeholder="whoami.container"
                    class="w-full font-mono"
                  />
                </UFormField>
                <UTextarea
                  v-model="written.content"
                  :rows="12"
                  autoresize
                  spellcheck="false"
                  class="w-full font-mono"
                  :ui="{ base: 'text-xs leading-5' }"
                  aria-label="Quadlet file"
                />
              </div>

              <HostError
                v-if="fetchError"
                :error="fetchError"
              />

              <p
                v-if="commit"
                class="flex items-center gap-2 text-sm text-muted"
              >
                <UIcon
                  name="i-lucide-git-commit-horizontal"
                  class="size-4"
                />
                Fetched commit <span class="font-mono text-highlighted">{{ commit }}</span> ·
                {{ pluralize(available.length, 'file') }} found
              </p>

              <UAlert
                v-if="manifestError"
                color="warning"
                variant="subtle"
                icon="i-lucide-file-warning"
                :title="manifestError"
              />

              <div
                v-if="manifest"
                class="flex flex-col gap-3"
              >
                <p
                  v-if="source !== 'catalog'"
                  class="flex items-center gap-2 text-sm"
                >
                  <UIcon
                    name="i-lucide-file-cog"
                    class="size-4 text-muted"
                  />
                  <span class="font-mono text-highlighted">pods.yml</span>
                  <span
                    v-if="manifest.description"
                    class="text-muted"
                    >· {{ manifest.description }}</span
                  >
                </p>
                <UFormField
                  v-if="sourceChoices.length"
                  label="Source"
                  :description="
                    activeSource ? undefined : 'Choose which of the repository\'s Quadlet directories to install.'
                  "
                >
                  <URadioGroup
                    v-model="chosenSource"
                    :items="sourceChoices"
                    orientation="horizontal"
                    variant="table"
                    :disabled="isFetching"
                    @update:model-value="chooseSource"
                  />
                </UFormField>
              </div>
            </div>
          </UCard>

          <UCard
            v-if="source !== 'write'"
            :ui="{ body: 'p-0 sm:p-0' }"
          >
            <template #header>
              <div class="flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-semibold text-highlighted">
                  <UBadge
                    label="2"
                    color="neutral"
                    variant="soft"
                  />
                  Quadlet files
                </h2>
                <span class="text-sm text-muted">{{ selected.length }} of {{ available.length }} selected</span>
              </div>
            </template>

            <ul
              v-if="available.length"
              class="divide-y divide-default"
            >
              <li
                v-for="file in available"
                :key="file.name"
              >
                <label class="flex cursor-pointer items-center gap-3 px-4 py-3">
                  <UCheckbox
                    :model-value="selected.includes(file.name)"
                    :disabled="!isQuadletFile(file.name)"
                    @update:model-value="toggle(file.name, $event)"
                  />
                  <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-mono text-sm text-highlighted">{{ file.name }}</span>
                    <span class="truncate font-mono text-xs text-muted">{{
                      isQuadletFile(file.name)
                        ? describeQuadlet(file)
                        : file.name === manifestFile
                          ? 'Pods manifest'
                          : 'not a Quadlet file'
                    }}</span>
                  </span>
                  <UBadge
                    :label="file.name.split('.').pop()"
                    color="neutral"
                    variant="outline"
                  />
                </label>
              </li>
            </ul>
            <p
              v-else
              class="p-4 text-sm text-muted"
            >
              {{
                source === 'catalog'
                  ? 'Choose an application from the catalog to see its files.'
                  : source === 'git'
                    ? 'Fetch a repository to see its files.'
                    : 'Upload files to see them here.'
              }}
            </p>

            <template
              v-if="available.length"
              #footer
            >
              <p class="text-sm text-muted">
                Files go to <span class="font-mono">{{ quadletDir }}/{{ application.name || 'NAME' }}/</span>.
                <template v-if="skipped.length">
                  {{ skipped.map((file) => file.name).join(', ') }}
                  {{
                    skipped.length === 1
                      ? "is skipped because it isn't a Quadlet file."
                      : "are skipped because they aren't Quadlet files."
                  }}
                </template>
              </p>
            </template>
          </UCard>

          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <div class="flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-semibold text-highlighted">
                  <UBadge
                    :label="source === 'write' ? '2' : '3'"
                    color="neutral"
                    variant="soft"
                  />
                  Secrets
                </h2>
                <UBadge
                  v-if="missing.length"
                  :label="`${missing.length} missing`"
                  color="warning"
                  variant="subtle"
                />
              </div>
            </template>

            <ul
              v-if="referenced.length"
              class="divide-y divide-default"
            >
              <li
                v-for="secret in referenced"
                :key="secret.name"
                class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
              >
                <span class="flex min-w-0 flex-1 flex-col">
                  <span class="flex items-center gap-2 font-mono text-sm text-highlighted">
                    <UIcon
                      :name="secret.existing ? 'i-lucide-key-round' : 'i-lucide-triangle-alert'"
                      :class="secret.existing ? 'text-success' : 'text-warning'"
                      class="size-4"
                    />
                    {{ secret.name }}
                  </span>
                  <span
                    v-if="secret.hint?.description"
                    class="text-sm text-toned"
                    >{{ secret.hint.description }}</span
                  >
                  <span class="truncate font-mono text-xs text-muted">
                    {{
                      secret.existing
                        ? `Already exists · ${secret.existing.driver ?? 'file'} driver${secret.existing.used_by.length ? ` · also used by ${secret.existing.used_by.join(', ')}` : ''}`
                        : secret.usedIn.join(', ')
                    }}
                  </span>
                  <span
                    v-if="!secret.existing && secret.hint?.from"
                    class="text-xs text-muted"
                  >
                    {{
                      missing.some((other) => other.name === secret.hint?.from)
                        ? `Filled in from ${secret.hint.key} in ${secret.hint.from} unless you enter a value`
                        : `${secret.hint.from} already exists, so enter it yourself`
                    }}
                  </span>
                </span>
                <UTextarea
                  v-if="!secret.existing && secret.hint?.format === 'dotenv'"
                  v-model="values[secret.name]"
                  :rows="4"
                  autoresize
                  :maxrows="12"
                  spellcheck="false"
                  autocomplete="off"
                  placeholder="Paste the .env file"
                  :aria-label="`Value of ${secret.name}`"
                  class="font-mono sm:w-80"
                  :ui="{ base: 'text-xs leading-5' }"
                />
                <div
                  v-else-if="!secret.existing"
                  class="flex gap-2 sm:w-80"
                >
                  <UInput
                    v-model="values[secret.name]"
                    type="password"
                    autocomplete="off"
                    :placeholder="isFilledIn(secret) ? `From ${secret.hint?.key}` : 'Enter a value'"
                    :aria-label="`Value of ${secret.name}`"
                    class="flex-1 font-mono"
                  />
                  <UButton
                    label="Generate"
                    color="neutral"
                    variant="outline"
                    @click="generate(secret.name)"
                  />
                </div>
              </li>
            </ul>
            <p
              v-else
              class="p-4 text-sm text-muted"
            >
              {{ files.length ? 'These files use no secrets.' : 'Secrets the files need show up here.' }}
            </p>
          </UCard>

          <UCard>
            <template #header>
              <h2 class="flex items-center gap-2 font-semibold text-highlighted">
                <UBadge
                  :label="source === 'write' ? '3' : '4'"
                  color="neutral"
                  variant="soft"
                />
                Options
              </h2>
            </template>

            <div class="flex flex-col gap-4">
              <UFormField
                label="Application name"
                required
                :error="form.errors.application"
              >
                <UInput
                  v-model="application.name"
                  :model-modifiers="{ trim: true }"
                  placeholder="nextcloud"
                  class="w-full font-mono sm:w-80"
                />
              </UFormField>
              <USwitch
                v-model="application.start"
                label="Start after installing"
                description="Runs systemctl start for the new units."
              />
              <USwitch
                v-model="application.replace"
                label="Replace existing files"
                description="podman quadlet install --replace, for updating an installed application."
              />
            </div>
          </UCard>
        </div>

        <div class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-0 lg:self-start">
          <UCard>
            <template #header>
              <div class="flex items-center justify-between">
                <h2 class="font-semibold text-highlighted">Summary</h2>
                <QuadletCheckStatus
                  :result="result"
                  :checking="checking"
                  :show-errors="false"
                />
              </div>
            </template>

            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm">
              <dt class="text-muted">Application</dt>
              <dd class="font-mono">{{ application.name || '–' }}</dd>
              <dt class="text-muted">Units</dt>
              <dd>{{ files.length ? unitsSummary : '–' }}</dd>
              <dt class="text-muted">Secrets</dt>
              <dd>{{ missing.length ? `${missing.length} to create` : 'none to create' }}</dd>
              <dt class="text-muted">Runs on</dt>
              <dd>{{ environment.hostname ?? environment.host }}</dd>
            </dl>

            <ul
              v-if="result?.data?.errors.length"
              class="mt-4 flex max-h-64 flex-col gap-1 overflow-y-auto border-t border-default pt-4 font-mono text-xs break-all text-error"
            >
              <li
                v-for="error in result.data.errors"
                :key="error"
              >
                {{ error }}
              </li>
            </ul>

            <template #footer>
              <div class="flex flex-col gap-3">
                <UAlert
                  v-for="error in serverErrors"
                  :key="error"
                  color="error"
                  variant="subtle"
                  :title="error"
                />
                <UButton
                  :label="`Install ${application.name || 'application'}`"
                  icon="i-lucide-download"
                  block
                  size="lg"
                  :loading="form.processing"
                  :disabled="!!blocker"
                  @click="submit"
                />
                <p class="text-center text-xs text-muted">
                  {{ blocker ?? 'Secret values are sent straight to podman secret create and never stored by Pods.' }}
                </p>
              </div>
            </template>
          </UCard>
        </div>
      </div>
    </template>
  </UDashboardPanel>
</template>
