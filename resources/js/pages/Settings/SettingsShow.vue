<script setup lang="ts">
import SettingsAccountForm from '@/components/Settings/SettingsAccountForm.vue'
import SettingsApplicationForm from '@/components/Settings/SettingsApplicationForm.vue'
import SettingsLayout from '@/components/Settings/SettingsLayout.vue'
import SettingsSecurityForm from '@/components/Settings/SettingsSecurityForm.vue'
import { Head } from '@inertiajs/vue3'
import { computed, type Component } from 'vue'

type Section = 'account' | 'security' | 'general'

const props = defineProps<{
  section: Section
}>()

const sections: Record<Section, { title: string; description: string; component: Component }> = {
  account: {
    title: 'Account',
    description: 'Your name, email address and session.',
    component: SettingsAccountForm,
  },
  security: {
    title: 'Security',
    description: 'Your password, two-factor authentication and passkeys.',
    component: SettingsSecurityForm,
  },
  general: {
    title: 'General',
    description: 'Settings of this Pods instance, shared by every user.',
    component: SettingsApplicationForm,
  },
}

const current = computed(() => sections[props.section])
</script>

<template>
  <Head :title="current.title" />

  <SettingsLayout
    :title="current.title"
    :description="current.description"
  >
    <UCard>
      <component
        :is="current.component"
        :key="section"
      />
    </UCard>
  </SettingsLayout>
</template>
