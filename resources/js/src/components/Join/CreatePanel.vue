<script setup lang="ts">
import { computed, ref } from 'vue'

const name = defineModel<string>('name', { required: true })
const gameCode = defineModel<string>('gameCode', { required: true })

const showMode = ref<'none' | 'create' | 'join'>('none')

const validName = computed(() => (name.value ?? '').trim().length > 0)
const canCreate = computed(() => validName.value)
const canJoin = computed(() => validName.value && !!gameCode.value)

const emit = defineEmits<{ (e:'create'):void; (e:'join'):void }>()
function handleCreate() { showMode.value = 'create' }
function handleJoin() { showMode.value = 'join' }
function confirmCreate() { emit('create') }
function confirmJoin() { emit('join') }
</script>

<template>
  <div class="space-y-3">
    <div v-if="showMode === 'none'" class="flex gap-2">
      <button @click="handleCreate" class="w-1/2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg">
        Spiel erstellen
      </button>
      <button @click="handleJoin" class="w-1/2 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg">
        Spiel beitreten
      </button>
    </div>

    <div v-if="showMode === 'create'" class="space-y-3">
      <input v-model="name" placeholder="Dein Name"
             class="w-full rounded-lg bg-slate-800 text-slate-200 placeholder:text-slate-500 border border-slate-600 px-3 py-2" />
      <button @click="confirmCreate" :disabled="!canCreate"
              class="w-full bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg disabled:opacity-50">
        Spiel erstellen
      </button>
    </div>

    <div v-if="showMode === 'join'" class="space-y-3">
      <input v-model="name" placeholder="Dein Name"
             class="w-full rounded-lg bg-slate-800 text-slate-200 placeholder:text-slate-500 border border-slate-600 px-3 py-2" />
      <input v-model="gameCode" placeholder="Game Code"
             class="w-full rounded-lg bg-slate-800 text-slate-200 placeholder:text-slate-500 border border-slate-600 px-3 py-2 font-mono" />
      <button @click="confirmJoin" :disabled="!canJoin"
              class="w-full bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg disabled:opacity-50">
        Spiel beitreten
      </button>
      <button @click="showMode = 'none'"
              class="w-full border border-blue-700 hover:bg-blue-700 text-slate-200 px-4 py-2 rounded-lg transition-colors">
        Zurück
      </button>
    </div>
  </div>
</template>
