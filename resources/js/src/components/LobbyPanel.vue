<script lang="ts" setup>
import { computed, unref } from 'vue';

const props = defineProps({
  gameCode: { type: [String, Object], required: true },
  isReady: { type: [Boolean, Object], required: true }
});
const gameCode = computed(() => unref(props.gameCode) as string);
const isReady = computed(() => unref(props.isReady) as boolean);
const copyToClip = () => {
  navigator.clipboard.writeText(gameCode.value);
};
</script>

<template>
  <div class="space-y-3 text-center">
    <p v-if="!isReady" class="font-medium text-slate-300">
      Code: <span class="font-mono text-lg text-blue-300">{{ gameCode }}</span>
      <i class="fa-solid fa-copy text-blue-300 ml-1 hover:text-blue-800 cursor-pointer" @click="copyToClip"></i>
    </p>
    <p v-if="!isReady" class="text-slate-400">Warte bis ein Gegner dem Spiel beitritt …</p>
    <p v-else class="flex justify-center items-center gap-2">
      <span class="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></span>
    </p>
  </div>
</template>
