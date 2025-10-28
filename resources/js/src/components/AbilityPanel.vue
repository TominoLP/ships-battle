<script setup lang="ts">
import type { AbilityType } from '@/src/composables/useAbilities';

interface Props {
  canUsePlane: boolean;
  canUseBomb: boolean;
  canUseSplatter: boolean;
  planeRemaining: number;
  planeTotal: number;
  bombRemaining: number;
  bombTotal: number;
  splatterRemaining: number;
  splatterTotal: number;
  planeExhausted: boolean;
  bombExhausted: boolean;
  splatterExhausted: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  startAbility: [type: AbilityType, event: PointerEvent];
  showError: [title: string, message: string];
}>();

function handleAbilityStart(type: AbilityType, ev: PointerEvent) {
  if (type === 'plane' && !props.canUsePlane) {
    emit('showError', 'Flugzeug verbraucht', 'Das Flugzeug kann nur einmal pro Spiel eingesetzt werden.');
    return;
  }
  if (type === 'comb' && !props.canUseBomb) {
    emit('showError', 'Bombe gesperrt', 'Die Bombe wird erst freigeschaltet, wenn du in diesem Zug 2 Schiffe versenkt hast.');
    return;
  }
  if (type === 'splatter' && !props.canUseSplatter) {
    emit('showError', 'Streuschuss verbraucht', 'Der Streuschuss kann nur zweimal pro Spiel eingesetzt werden.');
    return;
  }
  emit('startAbility', type, ev);
}
</script>

<template>
  <div class="grid gap-2">
    <!-- PLANE -->
    <button
      class="rounded-lg border px-3 py-2 text-left hover:bg-slate-800 border-slate-700 bg-slate-800/60 disabled:opacity-50"
      :disabled="!canUsePlane"
      title="Deckt eine komplette Zeile oder Spalte auf. R zum Drehen."
      @pointerdown.prevent="handleAbilityStart('plane', $event)"
    >
      <div class="flex items-start justify-between">
        <div>
          <div class="font-medium text-slate-100">Flugzeug</div>
          <div class="text-[11px] text-slate-400">Komplette Reihe/Spalte</div>
        </div>
        <span class="flex items-center gap-2">
          <span
            v-if="!planeExhausted"
            class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
            title="verbleibend / gesamt"
          >
            {{ planeRemaining }}/{{ planeTotal }}
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                   bg-emerald-600/15 text-emerald-300 border-emerald-500/40"
          >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path d="M20 6L9 17l-5-5" />
            </svg>
          </span>
        </span>
      </div>
    </button>

    <!-- BOMB -->
    <button
      class="rounded-lg border px-3 py-2 text-left hover:bg-slate-800 border-slate-700 bg-slate-800/60 disabled:opacity-50"
      :disabled="!canUseBomb || bombExhausted"
      :title="bombExhausted
        ? 'Du hast bereits die Bombe in diesem Spiel verbraucht.'
        : 'Trifft 5×5 ohne Ecken (21 Felder). 1 Bombe pro Spiel.'"
      @pointerdown.prevent="handleAbilityStart('comb', $event)"
    >
      <div class="flex items-start justify-between">
        <div>
          <div class="font-medium text-slate-100">Bombe</div>
          <div class="text-[11px] text-slate-400">
            <template v-if="bombExhausted">
              Bereits verwendet (max. 1 pro Spiel)
            </template>
            <template v-else>
              Sprengt großen Bereich
            </template>
          </div>
        </div>

        <span class="flex items-center gap-2">
          <span
            v-if="bombExhausted"
            class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
          >
            Aufgebraucht
          </span>
          <span
            v-else-if="!canUseBomb"
            class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
          >
            Gesperrt
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                   bg-emerald-600/15 text-emerald-300 border-emerald-500/40"
          >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path d="M20 6L9 17l-5-5" />
            </svg>
            Bereit
          </span>
        </span>
      </div>
    </button>

    <!-- SPLATTER -->
    <button
      class="rounded-lg border px-3 py-2 text-left hover:bg-slate-800 border-slate-700 bg-slate-800/60 disabled:opacity-50"
      :disabled="!canUseSplatter"
      title="Trifft 12 zufällige Felder."
      @pointerdown.prevent="handleAbilityStart('splatter', $event)"
    >
      <div class="flex items-start justify-between">
        <div>
          <div class="font-medium text-slate-100">Streuschuss</div>
          <div class="text-[11px] text-slate-400">12 zufällige Felder</div>
        </div>
        <span class="flex items-center gap-2">
          <span
            v-if="!splatterExhausted"
            class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
            title="verbleibend / gesamt"
          >
            {{ splatterRemaining }}/{{ splatterTotal }}
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                   bg-emerald-600/15 text-emerald-300 border-emerald-500/40"
          >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path d="M20 6L9 17l-5-5" />
            </svg>
          </span>
        </span>
      </div>
    </button>
  </div>
</template>