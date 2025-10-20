<script setup lang="ts">
import { computed, unref } from 'vue'

const props = defineProps({
  open: { type: [Boolean, Object], required: true },
  youWon: { type: [Boolean, Object, null], default: null },
  winnerName: { type: [String, Object], default: '' },
})
defineEmits(['close'])

const open = computed(() => unref(props.open) as boolean)
const youWon = computed(() => unref(props.youWon) as boolean | null)
const winnerName = computed(() => unref(props.winnerName) as string)
</script>

<template>
  <div v-if="open" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 w-[90%] max-w-sm text-center shadow-xl">
      <h3 class="text-2xl font-bold mb-2">
        {{ youWon ? 'You Win' : 'You Lose' }}
      </h3>
      <p class="text-gray-600 mb-4">
        Winner: <span class="font-semibold">{{ winnerName }}</span>
      </p>
      <button class="w-full bg-blue-600 text-white px-4 py-2 rounded" @click="$emit('close')">
        Back to lobby
      </button>
    </div>
  </div>
</template>
