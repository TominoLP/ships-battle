<script lang="ts" setup>
import { computed, ref, watch } from 'vue';

const name = defineModel<string>('name', { required: true });
const gameCode = defineModel<string>('gameCode', { required: true });

const showMode = ref<'none' | 'create' | 'join'>(gameCode.value ? 'join' : 'none');

const validName = computed(() => (name.value ?? '').trim().length > 0 && (name.value ?? '').trim().length <= 20 && (name.value ?? '').trim().length >= 3);
const canCreate = computed(() => validName.value);
const canJoin = computed(() => validName.value && !!gameCode.value && gameCode.value.trim().length === 6);

const emit = defineEmits<{ (e: 'create'): void; (e: 'join'): void }>();

function handleCreate() {
  showMode.value = 'create';
}

function handleJoin() {
  showMode.value = 'join';
}

function confirmCreate() {
  emit('create');
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
  <div class="space-y-3">
    <div v-if="showMode === 'none'" class="flex gap-2">
      <button class="w-1/2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg" @click="handleCreate">
        Spiel erstellen
      </button>
      <button class="w-1/2 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg" @click="handleJoin">
        Spiel beitreten
      </button>
    </div>

    <div v-if="showMode === 'create'" class="space-y-3">
      <input v-model="name" class="w-full rounded-lg bg-slate-800 text-slate-200 placeholder:text-slate-500 border border-slate-600 px-3 py-2"
             placeholder="Dein Name" />
      <button :disabled="!canCreate" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg disabled:opacity-50"
              @click="confirmCreate">
        Spiel erstellen
      </button>
    </div>

    <form v-if="showMode === 'join'" class="space-y-3" @submit.prevent="canJoin && confirmJoin()">
      <input v-model="name" class="w-full rounded-lg bg-slate-800 text-slate-200 placeholder:text-slate-500 border border-slate-600 px-3 py-2"
             placeholder="Dein Name" @keyup.enter="canJoin && confirmJoin()" />
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
  </div>
</template>
