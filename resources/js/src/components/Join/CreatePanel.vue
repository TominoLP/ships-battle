<script lang="ts" setup>
import { computed, ref, watch } from 'vue';

const gameCode = defineModel<string>('gameCode', { required: true });

const showMode = ref<'none' | 'create' | 'join'>(gameCode.value ? 'join' : 'none');

const canJoin = computed(() => !!gameCode.value && gameCode.value.trim().length === 6);

const emit = defineEmits<{ (e: 'create', options?: { public: boolean }): void; (e: 'join'): void }>();

const makePublic = ref(false);

function handleCreate() {
  showMode.value = 'create';
  makePublic.value = false;
}

function handleJoin() {
  showMode.value = 'join';
}

function confirmJoin() {
  emit('join');
}

function confirmCreate() {
  emit('create', { public: makePublic.value });
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

      <label class="flex items-start gap-3 rounded-lg border border-slate-700 bg-slate-800/70 px-4 py-3 text-sm text-slate-200">
        <input
          v-model="makePublic"
          type="checkbox"
          class="mt-1 h-4 w-4 rounded border-slate-500 bg-slate-900 text-emerald-500 focus:ring-emerald-500"
        >
        <span>
          <span class="block font-semibold">Öffentliches Spiel</span>
          <span class="text-xs text-slate-400">
            Aktivieren, damit dein Spiel in der öffentlichen Lobby erscheint und andere Spieler direkt beitreten können.
          </span>
        </span>
      </label>

      <div class="flex gap-2">
        <button
          type="button"
          class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-500"
          @click="confirmCreate"
        >
          Spiel starten
        </button>
        <button
          type="button"
          class="flex-1 rounded-lg border border-slate-600 px-4 py-2 text-slate-200 hover:bg-slate-700"
          @click="showMode = 'none'"
        >
          Abbrechen
        </button>
      </div>
    </div>
  </div>
</template>
