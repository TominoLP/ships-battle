<script lang="ts" setup>
import { computed, unref } from 'vue';

const props = defineProps({
  open: { type: [Boolean, Object], required: true },
  youWon: { type: [Boolean, Object, null], default: null },
  winnerName: { type: [String, Object], default: '' }
});
const emit = defineEmits<{
  (e: 'close'): void
}>();

const open = computed(() => unref(props.open) as boolean);
const youWon = computed(() => unref(props.youWon) as boolean | null);
const winnerName = computed(() => unref(props.winnerName) as string);

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

        <button
          type="button"
          class="w-full rounded-2xl px-4 py-2 font-semibold hover--pointer shadow-sm
                 bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          @click="onClose"
        >
          Neues Spiel
        </button>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
