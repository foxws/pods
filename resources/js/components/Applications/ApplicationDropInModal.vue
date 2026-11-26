<script setup lang="ts">
import { update } from '@/actions/Modules/Web/Applications/Controllers/ApplicationDropInController'
import FormModal from '@/components/Ui/FormModal.vue'
import type { EnvironmentData, QuadletFileData } from '@/types'
import { dropInTemplate, isLocalDropIn, localDropInName } from '@/utils/quadlet'
import { useLiveVisit } from '@/composables/hostActions'
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const open = defineModel<boolean>('open')

const props = defineProps<{
  environment: EnvironmentData
  application: string
  /** The Quadlets a new drop-in can be for. */
  quadlets: string[]
  /** The drop-in to edit, or null to add one. */
  file: QuadletFileData | null
}>()

const quadlet = ref('')
const baseName = ref('')

const form = useForm({ name: '', content: '', restart: true })

const isAdding = computed(() => props.file === null)

const name = computed(() =>
  props.file ? props.file.name : localDropInName(quadlet.value, baseName.value.trim() || 'override'),
)

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }

  quadlet.value = props.quadlets[0] ?? ''
  baseName.value = ''
  form.defaults({ name: '', content: props.file?.content ?? dropInTemplate(quadlet.value), restart: true })
  form.reset()
  form.clearErrors()
})

watch(quadlet, (current, previous) => {
  if (isAdding.value && form.content === dropInTemplate(previous ?? '')) {
    form.content = dropInTemplate(current)
  }
})

const liveVisit = useLiveVisit()

const onSubmit = (close: () => void): void => {
  if (props.file && form.content === props.file.content) {
    close()

    return
  }

  form
    .transform((data) => ({ ...data, name: name.value }))
    .put(update.url([props.environment.id, props.application]), {
      ...liveVisit(),
      onSuccess: () => close(),
    })
}
</script>

<template>
  <FormModal
    v-model:open="open"
    :title="file ? `Edit ${file.name}` : 'Add a drop-in'"
    :description="
      file
        ? file.path
        : 'A drop-in changes keys of a Quadlet without editing it. Pods saves it as a local drop-in (NAME.local.conf), which stays yours: updates leave it alone, and it never counts as a change outside Pods.'
    "
    :submit-label="form.restart ? 'Save and restart' : 'Save'"
    :processing="form.processing"
    :ui="{ content: 'sm:max-w-3xl' }"
    @submit="onSubmit"
  >
    <template #body>
      <div class="flex flex-col gap-4">
        <div
          v-if="isAdding"
          class="grid gap-4 sm:grid-cols-2"
        >
          <UFormField label="Quadlet">
            <USelect
              v-model="quadlet"
              :items="quadlets"
              class="w-full font-mono"
            />
          </UFormField>

          <UFormField
            label="Name"
            :error="form.errors.name"
            :hint="`.local.conf`"
          >
            <UInput
              v-model="baseName"
              placeholder="override"
              autocomplete="off"
              spellcheck="false"
              class="w-full font-mono"
            />
          </UFormField>
        </div>

        <p
          v-if="isAdding"
          class="font-mono text-xs text-muted"
        >
          {{ name }}
        </p>

        <p
          v-else-if="file && !isLocalDropIn(file.name)"
          class="text-sm text-muted"
        >
          Pods tracks this drop-in like the application's other files, since it isn't a local one (NAME.local.conf).
        </p>

        <UFormField
          label="Content"
          :error="form.errors.content"
        >
          <UTextarea
            v-model="form.content"
            :rows="12"
            autoresize
            :maxrows="28"
            spellcheck="false"
            autocomplete="off"
            class="w-full"
            :ui="{ base: 'font-mono text-xs leading-5' }"
          />
        </UFormField>

        <USwitch
          v-model="form.restart"
          :label="`Restart ${application} afterwards`"
          description="systemd only applies a drop-in when the unit restarts."
        />
      </div>
    </template>
  </FormModal>
</template>
