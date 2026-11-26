<script setup lang="ts">
/**
 * A UFormField with its label on the left and the control on the right, stacked on phones.
 * Every prop, attribute and slot goes through to UFormField.
 */
</script>

<template>
  <UFormField class="flex items-start justify-between gap-4 max-sm:flex-col">
    <template
      v-for="(_, name) in $slots"
      #[name]="slotProps"
    >
      <slot
        :name="name"
        v-bind="slotProps ?? {}"
      />
    </template>
  </UFormField>
</template>
