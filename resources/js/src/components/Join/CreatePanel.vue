<script lang="ts" setup>
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  userName: string;
}>();

const gameCode = defineModel<string>('gameCode', { required: true });

const showMode = ref<'none' | 'create' | 'join'>(gameCode.value ? 'join' : 'none');

const canJoin = computed(() => !!gameCode.value && gameCode.value.trim().length === 6);

const emit = defineEmits<{ (e: 'create'): void; (e: 'join'): void }>();

function handleCreate() {
  showMode.value = 'create';
  emit('create');
}

function handleJoin() {
  showMode.value = 'join';
}

function confirmJoin() {
  emit('join');
}

watch(gameCode, (code) => {
  if ((code ?? '').trim().length === 6 && showMode.value === 'none') {
    showMode.value = 'join';
  }
});
</script>

<template>
  <div class="space-y-4">
    <p class="text-sm text-slate-400">
      Erstelle ein neues Spiel oder trete einem bestehenden Spiel bei, indem du den Game Code eingibst.
    </p>

    <div v-if="showMode === 'none'" class="flex gap-2">
      <button class="w-1/2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg"
              @click="handleCreate">
        Spiel erstellen
      </button>
      <button class="w-1/2 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg"
              @click="handleJoin">
        Spiel beitreten
      </button>
    </div>

    <form v-if="showMode === 'join'" class="space-y-3" @submit.prevent="canJoin && confirmJoin()">
      <input v-model="gameCode" class="w-full rounded-lg bg-slate-800 text-slate-200 placeholder:text-slate-500 border border-slate-600 px-3 py-2 font-mono"
             placeholder="Game Code" maxlength="6" @keyup.enter="canJoin && confirmJoin()" />
      <button :disabled="!canJoin" class="w-full bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg disabled:opacity-50"
              type="submit">
        Spiel beitreten
      </button>
      <button class="w-full border border-blue-700 hover:bg-blue-700 text-slate-200 px-4 py-2 rounded-lg transition-colors"
              type="button" @click="showMode = 'none'">
        Zurück
      </button>
    </form>

    <div v-if="showMode === 'create'" class="space-y-3">
      <p class="text-sm text-slate-300">
        Wir haben ein neues Spiel für dich vorbereitet. Teile den Code mit deinem Gegner.
      </p>
    </div>
  </div>
</template>
