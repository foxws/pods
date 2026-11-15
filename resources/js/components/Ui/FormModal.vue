<script setup lang="ts">
import type { ModalProps, TabsItem } from '@nuxt/ui'
import UDrawer from '@nuxt/ui/components/Drawer.vue'
import UModal from '@nuxt/ui/components/Modal.vue'
import { useMediaQuery } from '@vueuse/core'
import { computed } from 'vue'

const open = defineModel<boolean>('open')

const props = withDefaults(
  defineProps<{
    title: string
    description?: string
    submitLabel?: string
    processing?: boolean
    tabs?: TabsItem[]
    ui?: ModalProps['ui']
  }>(),
  {
    submitLabel: 'Save changes',
  },
)

const emit = defineEmits<{
  submit: [close: () => void]
}>()

const isSheet = useMediaQuery('(max-width: 639px)')

const close = (): void => {
  open.value = false
}

const overlay = computed(() => (isSheet.value ? UDrawer : UModal))

const overlayUi = computed(() =>
  isSheet.value
    ? { footer: 'grid grid-cols-2 gap-2 pb-[env(safe-area-inset-bottom)]' }
    : { footer: 'justify-end', ...props.ui },
)

const onEnter = (event: KeyboardEvent): void => {
  if (event.defaultPrevented || !(event.target instanceof HTMLInputElement)) {
    return
  }

  event.preventDefault()
  emit('submit', close)
}
</script>

<template>
  <component
    :is="overlay"
    v-model:open="open"
    :title="title"
    :description="description"
    :ui="overlayUi"
  >
    <template
      v-if="$slots.default"
      #default
    >
      <slot />
    </template>

    <template #body>
      <div
        @submit.prevent="emit('submit', close)"
        @keydown.enter="onEnter($event)"
      >
        <UTabs
          v-if="tabs?.length"
          :items="tabs"
          variant="pill"
          color="neutral"
          size="sm"
          class="w-full gap-4"
          :ui="{
            list: 'rounded-lg bg-(--glass)',
            indicator: 'rounded-md bg-(--glass-strong) shadow-none',
            trigger: 'grow data-[state=active]:text-highlighted',
          }"
        >
          <template
            v-for="tab in tabs"
            :key="tab.value"
            #[tab.slot]
          >
            <slot :name="tab.slot" />
          </template>
        </UTabs>

        <slot
          v-else
          name="body"
        />
      </div>
    </template>

    <template #footer>
      <UButton
        label="Cancel"
        color="neutral"
        variant="soft"
        class="bg-(--glass) hover:bg-(--glass-strong)"
        size="lg"
        :block="isSheet"
        @click.prevent="close"
      />

      <UButton
        :label="submitLabel"
        color="primary"
        variant="solid"
        size="lg"
        :block="isSheet"
        :loading="processing"
        @click.prevent="emit('submit', close)"
      />
    </template>
  </component>
</template>
