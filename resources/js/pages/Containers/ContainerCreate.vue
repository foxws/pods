<script setup lang="ts">
import { index as containersIndex } from '@/actions/Modules/Web/Containers/Controllers/ContainerController'
import QuadletCheckStatus from '@/components/Quadlets/QuadletCheckStatus.vue'
import AppNavbar from '@/components/Ui/AppNavbar.vue'
import CodeBlock from '@/components/Ui/CodeBlock.vue'
import PageHeading from '@/components/Ui/PageHeading.vue'
import { useQuadletCheck, useQuadletInstall } from '@/composables/quadlets'
import type { ApplicationData, EnvironmentData, HostProperty, NetworkData, SecretData } from '@/types'
import { renderQuadlet, type QuadletFile } from '@/utils/quadlet'
import { Head, usePage } from '@inertiajs/vue3'
import type { BreadcrumbItem, SelectItem, TabsItem } from '@nuxt/ui'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps<{
  environment: EnvironmentData
  applications?: HostProperty<ApplicationData[]>
  networks?: HostProperty<NetworkData[]>
  secrets?: HostProperty<SecretData[]>
}>()

const page = usePage()
const prefillImage = new URL(page.url, 'http://pods.local').searchParams.get('image') ?? ''

type Port = { host: string; container: string; protocol: 'tcp' | 'udp' }
type Mount = { source: string; destination: string; mode: 'rw' | 'ro' }
type Variable = { name: string; value: string }
type SecretRef = { name: string; target: string; type: 'env' | 'mount' }

const fields = reactive({
  name: '',
  application: '',
  image: prefillImage,
  pod: '',
  network: '',
  ports: [] as Port[],
  mounts: [] as Mount[],
  variables: [] as Variable[],
  secrets: [] as SecretRef[],
  restart: 'on-failure',
  memory: '',
  health: '',
  autoUpdate: true,
  startAtBoot: true,
  readOnly: false,
})

const mode = ref<'form' | 'file'>('form')
const modes: TabsItem[] = [
  { label: 'Form', value: 'form', icon: 'i-lucide-list' },
  { label: 'Quadlet file', value: 'file', icon: 'i-lucide-file-code' },
]

const applicationName = computed(() => fields.application || fields.name)

const applicationItems = computed<SelectItem[]>(() => [
  { label: `New application${fields.name ? ` · ${fields.name}` : ''}`, value: '' },
  ...(props.applications?.data ?? []).map((application) => ({ label: application.name, value: application.name })),
])

const networkItems = computed<SelectItem[]>(() => [
  { label: 'Default (podman)', value: '' },
  ...(props.networks?.data ?? [])
    .filter((network) => network.quadlet?.endsWith('.network'))
    .map((network) => ({ label: network.quadlet!, value: network.quadlet! })),
])

const secretItems = computed<SelectItem[]>(() =>
  (props.secrets?.data ?? [])
    .filter((secret) => !secret.missing)
    .map((secret) => ({ label: secret.name, value: secret.name })),
)

const restartItems: SelectItem[] = [
  { label: 'on-failure', value: 'on-failure' },
  { label: 'always', value: 'always' },
  { label: 'no', value: 'no' },
]

/** Named volumes that get their own .volume quadlet, e.g. `uptime-kuma-data` in `uptime-kuma-data:/app/data`. */
const newVolumes = computed(() =>
  fields.mounts
    .map((mount) => mount.source.trim())
    .filter((source) => /^[A-Za-z0-9][A-Za-z0-9_.-]*$/.test(source) && !source.endsWith('.volume')),
)

const volumeSource = (source: string): string =>
  newVolumes.value.includes(source.trim()) ? `${source.trim()}.volume` : source.trim()

const generated = computed(() =>
  renderQuadlet(
    {
      Unit: [['Description', fields.name]],
      Container: [
        ['ContainerName', fields.name],
        ['Image', fields.image],
        ['AutoUpdate', fields.autoUpdate ? 'registry' : null],
        ['Pod', fields.pod],
        ['Network', fields.network],
        ['ReadOnly', fields.readOnly],
        ...fields.ports
          .filter((port) => port.container)
          .map<[string, string]>((port) => [
            'PublishPort',
            `${port.host || port.container}:${port.container}/${port.protocol}`,
          ]),
        ...fields.mounts
          .filter((mount) => mount.source && mount.destination)
          .map<[string, string]>((mount) => [
            'Volume',
            `${volumeSource(mount.source)}:${mount.destination}${mount.mode === 'ro' ? ':ro' : ''}`,
          ]),
        ...fields.variables
          .filter((variable) => variable.name)
          .map<[string, string]>((variable) => ['Environment', `${variable.name}=${variable.value}`]),
        ...fields.secrets
          .filter((secret) => secret.name)
          .map<[string, string]>((secret) => [
            'Secret',
            `${secret.name},type=${secret.type},target=${secret.target || secret.name}`,
          ]),
        ['HealthCmd', fields.health],
        ['Memory', fields.memory],
      ],
      Service: [['Restart', fields.restart === 'no' ? null : fields.restart]],
      Install: [['WantedBy', fields.startAtBoot ? 'default.target' : null]],
    },
    `${fields.name || 'NAME'}.container · managed by Pods`,
  ),
)

const edited = ref<string | null>(null)
const content = computed({
  get: () => edited.value ?? generated.value,
  set: (value) => {
    edited.value = value
  },
})

watch(mode, (current) => {
  if (current === 'form') {
    edited.value = null
  }
})

const files = computed<QuadletFile[]>(() =>
  fields.name
    ? [
        { name: `${fields.name}.container`, content: content.value },
        ...newVolumes.value.map((volume) => ({
          name: `${volume}.volume`,
          content: renderQuadlet({ Volume: [['VolumeName', volume]] }),
        })),
      ]
    : [],
)

const { result, checking } = useQuadletCheck(() => props.environment, files)
const { form, install } = useQuadletInstall(() => props.environment)

const submit = (start: boolean): void => {
  form.application = applicationName.value
  form.files = files.value
  form.start = start
  install()
}

const breadcrumb = computed<BreadcrumbItem[]>(() => [
  { label: 'Containers', to: containersIndex.url(props.environment.id) },
  { label: 'Add container' },
])

const fileErrors = computed(() =>
  Object.entries(form.errors)
    .filter(([key]) => key.startsWith('files'))
    .map(([, message]) => message),
)
</script>

<template>
  <Head title="Add container" />

  <UDashboardPanel id="container-create">
    <template #header>
      <AppNavbar
        :breadcrumb="breadcrumb"
        :back="{ label: 'Containers', to: containersIndex.url(environment.id) }"
      />
    </template>

    <template #body>
      <PageHeading
        title="Add container"
        description="Every field writes a line in a .container quadlet. Switch to the file view at any time to edit it by hand."
      />

      <UTabs
        v-model="mode"
        :items="modes"
        :content="false"
        size="sm"
        color="neutral"
        class="w-full sm:w-auto"
      />

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)]">
        <div
          v-if="mode === 'form'"
          class="flex min-w-0 flex-col gap-4"
        >
          <UCard>
            <template #header>
              <div class="flex items-baseline justify-between gap-2">
                <h2 class="font-semibold text-highlighted">General</h2>
                <span class="font-mono text-xs text-muted">Image= · Pod= · Network=</span>
              </div>
            </template>

            <div class="grid gap-4 sm:grid-cols-2">
              <UFormField
                label="Name"
                required
                :description="fields.name ? `Becomes ${fields.name}.container and ${fields.name}.service` : undefined"
                :error="form.errors.application"
              >
                <UInput
                  v-model="fields.name"
                  :model-modifiers="{ trim: true }"
                  autofocus
                  placeholder="uptime-kuma"
                  class="w-full font-mono"
                />
              </UFormField>

              <UFormField label="Application">
                <USelect
                  v-model="fields.application"
                  :items="applicationItems"
                  :loading="!applications"
                  class="w-full"
                />
              </UFormField>

              <UFormField
                label="Image"
                required
                class="sm:col-span-2"
              >
                <UInput
                  v-model="fields.image"
                  :model-modifiers="{ trim: true }"
                  placeholder="docker.io/louislam/uptime-kuma:2"
                  class="w-full font-mono"
                />
              </UFormField>

              <UFormField
                label="Pod"
                description="A .pod quadlet to join, e.g. immich.pod."
              >
                <UInput
                  v-model="fields.pod"
                  placeholder="None"
                  class="w-full font-mono"
                />
              </UFormField>

              <UFormField label="Network">
                <USelect
                  v-model="fields.network"
                  :items="networkItems"
                  :loading="!networks"
                  class="w-full font-mono"
                />
              </UFormField>
            </div>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-baseline justify-between gap-2">
                <h2 class="font-semibold text-highlighted">Ports</h2>
                <span class="font-mono text-xs text-muted">PublishPort=</span>
              </div>
            </template>

            <div class="flex flex-col gap-2">
              <div
                v-for="(port, index) in fields.ports"
                :key="index"
                class="grid grid-cols-[1fr_1fr_6rem_auto] gap-2"
              >
                <UInput
                  v-model="port.host"
                  placeholder="Host"
                  aria-label="Host port"
                  class="font-mono"
                />
                <UInput
                  v-model="port.container"
                  placeholder="Container"
                  aria-label="Container port"
                  class="font-mono"
                />
                <USelect
                  v-model="port.protocol"
                  :items="['tcp', 'udp']"
                  aria-label="Protocol"
                />
                <UButton
                  icon="i-lucide-x"
                  color="neutral"
                  variant="ghost"
                  aria-label="Remove port"
                  @click="fields.ports.splice(index, 1)"
                />
              </div>
              <UButton
                label="Add port"
                icon="i-lucide-plus"
                color="neutral"
                variant="outline"
                class="self-start border-dashed"
                @click="fields.ports.push({ host: '', container: '', protocol: 'tcp' })"
              />
            </div>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-baseline justify-between gap-2">
                <h2 class="font-semibold text-highlighted">Volumes</h2>
                <span class="font-mono text-xs text-muted">Volume=</span>
              </div>
            </template>

            <div class="flex flex-col gap-2">
              <div
                v-for="(mount, index) in fields.mounts"
                :key="index"
                class="grid grid-cols-[1fr_1fr_5rem_auto] gap-2"
              >
                <UInput
                  v-model="mount.source"
                  placeholder="Volume or host path"
                  aria-label="Source"
                  class="font-mono"
                />
                <UInput
                  v-model="mount.destination"
                  placeholder="/app/data"
                  aria-label="Path in the container"
                  class="font-mono"
                />
                <USelect
                  v-model="mount.mode"
                  :items="['rw', 'ro']"
                  aria-label="Mode"
                />
                <UButton
                  icon="i-lucide-x"
                  color="neutral"
                  variant="ghost"
                  aria-label="Remove volume"
                  @click="fields.mounts.splice(index, 1)"
                />
              </div>
              <div class="flex flex-wrap items-center gap-3">
                <UButton
                  label="Add volume"
                  icon="i-lucide-plus"
                  color="neutral"
                  variant="outline"
                  class="border-dashed"
                  @click="fields.mounts.push({ source: '', destination: '', mode: 'rw' })"
                />
                <span
                  v-if="newVolumes.length"
                  class="text-sm text-muted"
                  >Pods also creates {{ newVolumes.map((volume) => `${volume}.volume`).join(', ') }}</span
                >
              </div>
            </div>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-baseline justify-between gap-2">
                <h2 class="font-semibold text-highlighted">Environment and secrets</h2>
                <span class="font-mono text-xs text-muted">Environment= · Secret=</span>
              </div>
            </template>

            <div class="flex flex-col gap-4">
              <div class="flex flex-col gap-2">
                <div
                  v-for="(variable, index) in fields.variables"
                  :key="index"
                  class="grid grid-cols-[1fr_1fr_auto] gap-2"
                >
                  <UInput
                    v-model="variable.name"
                    placeholder="TZ"
                    aria-label="Variable name"
                    class="font-mono"
                  />
                  <UInput
                    v-model="variable.value"
                    placeholder="Europe/Amsterdam"
                    aria-label="Value"
                    class="font-mono"
                  />
                  <UButton
                    icon="i-lucide-x"
                    color="neutral"
                    variant="ghost"
                    aria-label="Remove variable"
                    @click="fields.variables.splice(index, 1)"
                  />
                </div>
                <UButton
                  label="Add variable"
                  icon="i-lucide-plus"
                  color="neutral"
                  variant="outline"
                  class="self-start border-dashed"
                  @click="fields.variables.push({ name: '', value: '' })"
                />
              </div>

              <div class="flex flex-col gap-2">
                <p class="text-sm font-medium text-highlighted">Secrets</p>
                <div
                  v-for="(secret, index) in fields.secrets"
                  :key="index"
                  class="grid grid-cols-[1fr_1fr_6rem_auto] gap-2"
                >
                  <USelect
                    v-model="secret.name"
                    :items="secretItems"
                    :loading="!secrets"
                    placeholder="Secret"
                    aria-label="Secret"
                    class="font-mono"
                  />
                  <UInput
                    v-model="secret.target"
                    placeholder="SMTP_PASSWORD"
                    aria-label="Target"
                    class="font-mono"
                  />
                  <USelect
                    v-model="secret.type"
                    :items="['env', 'mount']"
                    aria-label="Type"
                  />
                  <UButton
                    icon="i-lucide-x"
                    color="neutral"
                    variant="ghost"
                    aria-label="Remove secret"
                    @click="fields.secrets.splice(index, 1)"
                  />
                </div>
                <UButton
                  label="Add secret"
                  icon="i-lucide-key-round"
                  color="neutral"
                  variant="outline"
                  class="self-start border-dashed"
                  :disabled="!secretItems.length"
                  @click="fields.secrets.push({ name: '', target: '', type: 'env' })"
                />
              </div>
            </div>
          </UCard>

          <UCard>
            <template #header>
              <div class="flex items-baseline justify-between gap-2">
                <h2 class="font-semibold text-highlighted">Runtime</h2>
                <span class="font-mono text-xs text-muted">[Service] · [Install]</span>
              </div>
            </template>

            <div class="flex flex-col gap-4">
              <div class="grid gap-4 sm:grid-cols-2">
                <UFormField label="Restart">
                  <USelect
                    v-model="fields.restart"
                    :items="restartItems"
                    class="w-full"
                  />
                </UFormField>
                <UFormField label="Memory limit">
                  <UInput
                    v-model="fields.memory"
                    placeholder="512m"
                    class="w-full font-mono"
                  />
                </UFormField>
              </div>

              <UFormField label="Health check">
                <UInput
                  v-model="fields.health"
                  placeholder="curl -f http://localhost:3001 || exit 1"
                  class="w-full font-mono"
                />
              </UFormField>

              <USwitch
                v-model="fields.autoUpdate"
                label="Auto-update image"
                description="AutoUpdate=registry, picked up by podman-auto-update.timer"
              />
              <USwitch
                v-model="fields.startAtBoot"
                label="Start at boot"
                description="WantedBy=default.target"
              />
              <USwitch
                v-model="fields.readOnly"
                label="Read-only root filesystem"
                description="ReadOnly=true"
              />
            </div>
          </UCard>
        </div>

        <div class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-0 lg:self-start">
          <UCard
            v-if="mode === 'file'"
            :ui="{ body: 'p-0 sm:p-0' }"
          >
            <template #header>
              <span class="font-mono text-sm font-medium text-highlighted">{{ fields.name || 'NAME' }}.container</span>
            </template>
            <UTextarea
              v-model="content"
              :rows="24"
              autoresize
              spellcheck="false"
              variant="none"
              class="w-full font-mono"
              :ui="{ base: 'p-4 text-xs leading-5' }"
              aria-label="Quadlet file"
            />
          </UCard>

          <CodeBlock
            v-else
            :title="`${fields.name || 'NAME'}.container`"
            :subtitle="`Saved to ${environment.scope === 'rootful' ? '/etc' : '~/.config'}/containers/systemd/${applicationName || 'NAME'}/`"
            :content="content"
          >
            <template #actions>
              <QuadletCheckStatus
                :result="result"
                :checking="checking"
              />
            </template>
          </CodeBlock>

          <QuadletCheckStatus
            v-if="mode === 'file'"
            :result="result"
            :checking="checking"
          />

          <UAlert
            v-for="error in fileErrors"
            :key="error"
            color="error"
            variant="subtle"
            :title="error"
          />

          <div class="flex flex-wrap justify-end gap-2">
            <UButton
              label="Cancel"
              color="neutral"
              variant="ghost"
              :to="containersIndex.url(environment.id)"
            />
            <UButton
              label="Save"
              color="neutral"
              variant="outline"
              :disabled="!fields.name || !fields.image || form.processing"
              @click="submit(false)"
            />
            <UButton
              label="Save and start"
              icon="i-lucide-play"
              :loading="form.processing"
              :disabled="!fields.name || !fields.image || result?.data?.valid === false"
              @click="submit(true)"
            />
          </div>
        </div>
      </div>
    </template>
  </UDashboardPanel>
</template>
