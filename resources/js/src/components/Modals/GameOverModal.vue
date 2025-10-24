<script lang="ts" setup>
import { computed, unref } from 'vue';

const props = defineProps({
  open: { type: [Boolean, Object], required: true },
  youWon: { type: [Boolean, Object, null], default: null },
  winnerName: { type: [String, Object], default: '' },
  rematchState: { type: [String, Object], default: 'idle' },
  rematchError: { type: [String, Object, null], default: null }
});
const emit = defineEmits<{
  (e: 'close'): void
  (e: 'rematch'): void
}>();

const open = computed(() => unref(props.open) as boolean);
const youWon = computed(() => unref(props.youWon) as boolean | null);
const winnerName = computed(() => unref(props.winnerName) as string);
const rematchState = computed(() => unref(props.rematchState) as 'idle' | 'waiting' | 'ready');
const rematchError = computed(() => {
  const msg = unref(props.rematchError);
  return typeof msg === 'string' && msg.length > 0 ? msg : null;
});

const isDisabled = computed(() => rematchState.value === 'waiting' || rematchState.value === 'ready');
const buttonLabel = computed(() => {
  if (rematchState.value === 'waiting') return 'Warte auf Gegner …';
  if (rematchState.value === 'ready') return 'Starte neue Runde …';
  return 'Rematch starten';
});
const statusMessage = computed(() => {
  if (rematchState.value === 'waiting') {
    return 'Sobald dein Gegner zustimmt, startet ihr automatisch eine neue Runde.';
  }
  if (rematchState.value === 'ready') {
    return 'Rematch akzeptiert – neues Spiel wird vorbereitet.';
  }
  return null;
});

function onRematch() {
  if (isDisabled.value) return;
  emit('rematch');
}

function onClose() {
  emit('close');
}
</script>

<template>
  <transition name="fade">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
      @click.self="onClose"
    >
      <div
        class=" rounded-2xl shadow-md border w-[90%] max-w-sm p-6 text-center"
        role="dialog"
        aria-modal="true"
      >
        <h3 class="text-2xl font-bold mb-1">
          {{ youWon ? 'Gewonnen' : 'Verloren' }}
        </h3>
        <p class="text-gray-600 mb-5">
          Winner:
          <span class="font-semibold">{{ winnerName || '—' }}</span>
        </p>

        <div class="space-y-3">
          <button
            type="button"
            :disabled="isDisabled"
            class="w-full rounded-2xl px-4 py-2 font-semibold shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
            :class="isDisabled ? 'bg-blue-500/70 cursor-wait' : 'bg-blue-600 hover:bg-blue-700'"
            @click="onRematch"
          >
            {{ buttonLabel }}
          </button>

          <button
            type="button"
            class="w-full rounded-2xl px-4 py-2 font-semibold shadow-sm border border-slate-300/40 text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400"
            @click="onClose"
          >
            Zurück zur Lobby
          </button>
        </div>

        <p
          v-if="statusMessage"
          class="mt-4 text-sm text-slate-500"
        >
          {{ statusMessage }}
        </p>
        <p
          v-if="rematchError"
          class="mt-2 text-sm text-rose-500"
        >
          {{ rematchError }}
        </p>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
