<script lang="ts" setup>
import { computed } from 'vue';
import { useLang } from '@/src/composables/useLang';

const { t, locale, supported, setLocale } = useLang();
const options = computed(() =>
  supported.map(l => ({ value: l, label: t(`lang.${l}`) }))
);

function onChange(e: Event) {
  const value = (e.target as HTMLSelectElement).value as any;
  setLocale(value);
}
</script>

<template>
  <label class="inline-flex items-center gap-2 text-sm text-slate-300">
    <i class="fa-solid fa-globe"></i>
    <span class="sr-only sm:not-sr-only">{{ t('lang.label') }}:</span>
    <select
      :value="locale"
      @change="onChange"
      class="rounded-md border border-slate-600 bg-slate-800 px-2 py-1 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/60"
    >
      <option v-for="opt in options" :key="opt.value" :value="opt.value">
        {{ opt.label }}
      </option>
    </select>
  </label>
</template>
