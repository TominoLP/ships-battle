<script lang="ts" setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { Step } from '@/src/types';

type AbilityUsage = {
  plane: number;
  comb: number;
  splatter: number;
};

const props = defineProps<{
  myBoard: number[][];
  enemyBoard: number[][];
  enemyName: string;
  abilityUsage: AbilityUsage;
  enemySunkShips: number[];
  step: Step;
  gameOver: boolean;
  myTurn: boolean;
}>();

function flatten(board: number[][] | undefined): number[] {
  if (!board) return [];
  return board.flatMap(row => row ?? []);
}

const playerShots = computed(() =>
  flatten(props.enemyBoard).filter(cell => cell === 1 || cell === 2).length
);
const playerHits = computed(() =>
  flatten(props.enemyBoard).filter(cell => cell === 2).length
);
const playerAccuracy = computed(() => {
  const total = playerShots.value;
  if (!total) return null;
  return (playerHits.value / total) * 100;
});

const enemyShots = computed(() =>
  flatten(props.myBoard).filter(cell => cell === 3 || cell === 4).length
);
const enemyHits = computed(() =>
  flatten(props.myBoard).filter(cell => cell === 4).length
);
const enemyAccuracy = computed(() => {
  const total = enemyShots.value;
  if (!total) return null;
  return (enemyHits.value / total) * 100;
});

const playerShipsRemaining = computed(() =>
  flatten(props.myBoard).filter(cell => cell === 1).length
);
const shipsSunk = computed(() => props.enemySunkShips.length);

const elapsedMs = ref(0);
let startAt: number | null = null;
let ticker: number | null = null;

function startTimer() {
  if (ticker) return;
  startAt = Date.now();
  ticker = window.setInterval(() => {
    if (startAt !== null) {
      elapsedMs.value = Date.now() - startAt;
    }
  }, 1000);
}

function stopTimer(reset = false) {
  if (ticker) {
    clearInterval(ticker);
    ticker = null;
  }
  if (reset) {
    elapsedMs.value = 0;
    startAt = null;
  }
}

watch(() => props.step, (step, prev) => {
  if (step === 'playing') {
    elapsedMs.value = 0;
    startTimer();
  } else if (prev === 'playing' && step !== 'playing') {
    stopTimer(step === 'join');
  } else if (step === 'join') {
    stopTimer(true);
  }
}, { immediate: true });

watch(() => props.gameOver, (over) => {
  if (over) {
    stopTimer(false);
  }
});

onBeforeUnmount(() => stopTimer(false));

const elapsedSeconds = computed(() => Math.floor(elapsedMs.value / 1000));
const formattedTime = computed(() => {
  const total = elapsedSeconds.value;
  const minutes = Math.floor(total / 60).toString().padStart(2, '0');
  const seconds = (total % 60).toString().padStart(2, '0');
  return `${minutes}:${seconds}`;
});

const turnLabel = computed(() => {
  if (props.gameOver) {
    return 'Spiel beendet';
  }
  if (props.step !== 'playing') {
    return 'Spielvorbereitung';
  }
  return props.myTurn ? 'Du bist am Zug' : `${props.enemyName || 'Gegner'} ist dran`;
});

function formatAccuracy(value: number | null): string {
  if (value === null) return '–';
  return `${value.toFixed(1)} %`;
}
</script>

<template>
  <section class="space-y-4">
    <header class="flex items-start justify-between">
      <div class="space-y-1">
        <h3 class="text-lg font-semibold text-slate-100">Statistik</h3>
      </div>
      <div class="rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-1 text-xs font-medium text-slate-200">
        <p class="text-xs font-mono text-slate-400">Spielzeit <span class="text-slate-200">{{ formattedTime }}</span></p>
      </div>
    </header>

    <div class="space-y-4 divide-y divide-slate-800 rounded-lg border border-slate-700 bg-slate-900/60">
      <div class="p-4">
        <h4 class="mb-3 text-sm font-semibold text-emerald-300 uppercase tracking-wide">Deine Angriffe</h4>
        <dl class="space-y-2 text-sm text-slate-300">
          <div class="flex justify-between">
            <dt>Abgefeuerte Schüsse</dt>
            <dd class="font-semibold text-emerald-300">{{ playerShots }}</dd>
          </div>
          <div class="flex justify-between border-t border-slate-800 pt-2">
            <dt>Treffer</dt>
            <dd class="font-semibold text-emerald-300">{{ playerHits }}</dd>
          </div>
          <div class="flex justify-between border-t border-slate-800 pt-2">
            <dt>Treffgenauigkeit</dt>
            <dd class="font-semibold text-emerald-300">{{ formatAccuracy(playerAccuracy) }}</dd>
          </div>
          <div class="flex justify-between border-t border-slate-800 pt-2">
            <dt>Schiffe versenkt</dt>
            <dd class="font-semibold text-emerald-300">{{ shipsSunk }}</dd>
          </div>
        </dl>
      </div>
    </div>
    <div class="space-y-4 divide-y divide-slate-800 rounded-lg border border-slate-700 bg-slate-900/60">
      <div class="p-4">
        <h4 class="mb-3 text-sm font-semibold text-red-500 uppercase tracking-wide">Angriffe von {{ enemyName || 'Gegner' }}</h4>
        <dl class="space-y-2 text-sm text-slate-300">
          <div class="flex justify-between">
            <dt>Abgefeuerte Schüsse</dt>
            <dd class="font-semibold text-rose-300">{{ enemyShots }}</dd>
          </div>
          <div class="flex justify-between border-t border-slate-800 pt-2">
            <dt>Treffer</dt>
            <dd class="font-semibold text-rose-300">{{ enemyHits }}</dd>
          </div>
          <div class="flex justify-between border-t border-slate-800 pt-2">
            <dt>Treffgenauigkeit</dt>
            <dd class="font-semibold text-rose-300">{{ formatAccuracy(enemyAccuracy) }}</dd>
          </div>
          <div class="flex justify-between border-t border-slate-800 pt-2">
            <dt>Deine intakten Felder</dt>
            <dd class="font-semibold text-rose-300">{{ playerShipsRemaining }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </section>
</template>
