import { inject, provide, ref, type InjectionKey, type Ref } from 'vue'

type SettingsDialog = {
  isOpen: Ref<boolean>
  section: Ref<string>
  open: (section?: string) => void
}

const settingsKey: InjectionKey<SettingsDialog> = Symbol('settings')

/**
 * Creates the settings dialog state for a layout. The layout renders the dialog once; any descendant can open it.
 */
export function provideSettings(): SettingsDialog {
  const isOpen = ref(false)
  const section = ref('account')

  const dialog: SettingsDialog = {
    isOpen,
    section,
    open: (target = 'account') => {
      section.value = target
      isOpen.value = true
    },
  }

  provide(settingsKey, dialog)

  return dialog
}

export function useSettings(): SettingsDialog {
  const dialog = inject(settingsKey)

  if (!dialog) {
    throw new Error('useSettings() needs a layout that calls provideSettings().')
  }

  return dialog
}
