<script setup lang="ts">
import { computed, unref } from 'vue'
const props = defineProps({
  gameCode: { type: [String, Object], required: true },
  isReady: { type: [Boolean, Object], required: true },
})
const gameCode = computed(() => unref(props.gameCode) as string)
const isReady = computed(() => unref(props.isReady) as boolean)
</script>

<template>
  <div class="space-y-3 text-center">
    <p class="font-medium text-slate-300">
      Code: <span class="font-mono text-lg text-blue-300">{{ gameCode }}</span>
    </p>
    <p v-if="!isReady" class="text-slate-400">Warte bis das Gegner-Spiel startet…</p>
    <p v-else class="text-emerald-400">Beide Spieler bereit…</p>
  </div>
</template>