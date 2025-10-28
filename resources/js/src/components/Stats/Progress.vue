<script lang="ts" setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import type { LevelInfo, AchievementItem, StepsLike } from '../../types';

const props = defineProps<{
  open: boolean;
  level: LevelInfo | null | undefined;
  achievements: AchievementItem[] | null | undefined;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void; (e: 'close'): void }>();

const isOpen = computed({
  get: () => props.open,
  set: (v: boolean) => emit('update:open', v),
});

function close() {
  isOpen.value = false;
  emit('close');
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && isOpen.value) {
    e.preventDefault();
    close();
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

const levelPercent = computed(() => {
  const lvl = props.level;
  if (!lvl) return 0;
  if (!lvl.current && !lvl.next) return 0;
  if (!lvl.next) return 100;
  const base = lvl.current?.min_points ?? 0;
  const total = Math.max(1, lvl.next.min_points - base);
  const gained = Math.max(0, Math.min(lvl.points - base, total));
  return Math.round((gained / total) * 100);
});

const levelLabel = computed(() => {
  const lvl = props.level;
  if (!lvl) return 'Level';
  return `${lvl.current?.name ?? 'Level'}`;
});

function achievementStatus(a: AchievementItem): string {
  if (a.type === 'event') return a.progress.completed ? 'Freigeschaltet' : 'Noch nicht freigeschaltet';
  const next = a.progress.next_step;
  if (a.progress.completed) return `Abgeschlossen (${a.progress.value})`;
  if (next != null) return `${a.progress.value} / ${next}`;
  return `${a.progress.value}`;
}

/* Steps helpers (support Map and plain object) */
function isMap(s: StepsLike): s is Map<number, number> {
  return typeof (s as any)?.get === 'function' && typeof (s as any)?.keys === 'function';
}
function stepCount(a: AchievementItem): number {
  const s = a.steps;
  return isMap(s) ? s.size : Object.keys(s ?? {}).length;
}
function stepThresholdsSorted(a: AchievementItem): number[] {
  const s = a.steps;
  const keys = isMap(s) ? Array.from(s.keys()) : Object.keys(s ?? {}).map(Number);
  return keys.sort((x, y) => x - y);
}
function stepPoints(a: AchievementItem, t: number): number {
  const s = a.steps;
  if (isMap(s)) return s.get(t) ?? 0;
  const v = (s as Record<string, number>)[String(t)];
  return typeof v === 'number' ? v : 0;
}
function lastThreshold(a: AchievementItem): number | null {
  const th = stepThresholdsSorted(a);
  return th.length ? th[th.length - 1] : null;
}
function nextStepPoints(a: AchievementItem): number | null {
  const next = a.progress.next_step;
  if (next == null) return null;
  return stepPoints(a, next);
}

function achievementPercent(a: AchievementItem): number {
  if (a.type === 'event') return a.progress.completed ? 100 : 0;
  const fallbackMax = lastThreshold(a) ?? 0;
  const next = a.progress.next_step ?? fallbackMax;
  if (!next) return 0;
  const val = Math.max(0, Math.min(a.progress.value, next));
  return Math.round((val / next) * 100);
}

const filter = ref<'all' | 'inprogress' | 'done'>('all');
const filteredAchievements = computed(() => {
  const list = props.achievements ?? [];
  if (filter.value === 'done') return list.filter(a => a.progress.completed);
  if (filter.value === 'inprogress') return list.filter(a => !a.progress.completed && a.progress.value > 0);
  return list;
});
</script>

<template>
  <transition name="fade">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/55"
      @click.self="close"
      role="dialog"
      aria-modal="true"
    >
      <div class="w-[min(1400px,92vw)] max-h-[88vh] overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between gap-3 border-b border-slate-700/70 px-5 py-4">
          <div class="flex items-center gap-3">
            <i class="fa-solid fa-trophy text-amber-300"></i>
            <h2 class="text-lg font-semibold text-slate-100">Fortschritt & Erfolge</h2>
          </div>
          <button class="rounded-md border border-slate-600 px-3 py-1.5 text-slate-200 hover:bg-slate-800" @click="close" aria-label="Schließen">
            Schließen
          </button>
        </div>

        <div class="overflow-y-auto p-5">
          <div class="rounded-xl border border-slate-700/70 bg-slate-900/70 p-5">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-4">
              <div>
                <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Aktueller Rang</div>
                <div class="mt-1 text-2xl font-bold text-slate-100">{{ levelLabel }}</div>
              </div>
              <div class="text-right">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Gesamtpunkte</div>
                <div class="mt-1 text-2xl font-bold text-amber-400">{{ props.level?.points ?? 0 }}</div>
              </div>
            </div>

            <div class="h-3 w-full overflow-hidden rounded-full border border-slate-700 bg-slate-800">
              <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-[width]" :style="{ width: `${levelPercent}%` }" />
            </div>

            <div class="mt-2.5 flex items-center justify-between text-xs text-slate-400">
              <div class="flex items-center gap-1.5">
                {{ props.level?.current?.name ?? 'Level' }}
                <span v-if="props.level?.current" class="opacity-60">• {{ props.level.current.min_points }} P</span>
              </div>
              <div v-if="props.level?.next" class="font-medium">
                {{ props.level.next.name }} <span class="text-amber-400">• {{ props.level.next.points_to_go }} P</span>
              </div>
              <div v-else class="font-medium text-amber-300">Max. Level erreicht ✓</div>
            </div>
          </div>
          
          <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <div class="text-slate-300">Erfolge</div>
            <div class="flex gap-2">
              <button
                class="rounded-md border px-3 py-1.5 text-xs transition"
                :class="filter === 'all' ? 'border-amber-400 text-amber-300 bg-slate-800/60' : 'border-slate-600 text-slate-300 hover:bg-slate-800'"
                @click="filter = 'all'"
              >Alle</button>
              <button
                class="rounded-md border px-3 py-1.5 text-xs transition"
                :class="filter === 'inprogress' ? 'border-amber-400 text-amber-300 bg-slate-800/60' : 'border-slate-600 text-slate-300 hover:bg-slate-800'"
                @click="filter = 'inprogress'"
              >In Arbeit</button>
              <button
                class="rounded-md border px-3 py-1.5 text-xs transition"
                :class="filter === 'done' ? 'border-amber-400 text-amber-300 bg-slate-800/60' : 'border-slate-600 text-slate-300 hover:bg-slate-800'"
                @click="filter = 'done'"
              >Abgeschlossen</button>
            </div>
          </div>
          
          <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="a in filteredAchievements" :key="a.key" class="rounded-xl border border-slate-700 bg-slate-900/70 p-4 transition-shadow hover:shadow-lg hover:shadow-slate-900/50">
              <div class="mb-3 flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5">
                  <i
                    class="fa-solid text-lg"
                    :class="a.type === 'event'
                      ? (a.progress.completed ? 'fa-bolt text-amber-300' : 'fa-bolt text-slate-500')
                      : (a.progress.completed ? 'fa-medal text-amber-300' : 'fa-flag text-slate-400')"
                  />
                  <div class="font-semibold text-slate-100">{{ a.name }}</div>
                </div>
                <span
                  class="shrink-0 rounded-full border px-2.5 py-0.5 text-[10px] font-medium uppercase tracking-wider"
                  :class="a.progress.completed ? 'border-emerald-500/60 bg-emerald-500/10 text-emerald-400' : 'border-slate-600 bg-slate-800/40 text-slate-400'"
                >
                  {{ a.type === 'event' ? (a.progress.completed ? 'Freigeschaltet' : 'Event') : 'Stufen' }}
                </span>
              </div>

              <div v-if="a.description" class="mb-3 text-xs leading-relaxed text-slate-400">
                {{ a.description }}
              </div>

              <div class="mb-3 space-y-1.5">
                <div
                  v-if="a.type === 'event' || a.progress.completed || (a.type === 'counter' && a.progress.next_step == null)"
                  class="flex items-center justify-between text-xs"
                >
                  <span class="text-slate-300">{{ achievementStatus(a) }}</span>
                  <span class="rounded-md bg-slate-800 px-2 py-0.5 font-medium text-amber-400">
                    +{{ a.event_points }}P
                  </span>
                </div>
                
                <div v-if="a.type === 'counter' && a.progress.next_step != null && !a.progress.completed" class="flex items-center justify-between text-xs">
                  <span class="text-slate-400">Nächste Stufe: <strong class="text-slate-300">{{ a.progress.next_step - a.progress.value }}</strong></span>
                  <span class="flex items-center gap-2 text-slate-400">
                    <span class="rounded-md bg-slate-800 px-1.5 py-0.5 text-[10px] font-medium text-emerald-400">+{{ nextStepPoints(a) ?? 0 }}P</span>
                  </span>
                </div>
              </div>

              <div class="h-2 w-full overflow-hidden rounded-full border border-slate-700 bg-slate-800">
                <div
                  class="h-full rounded-full"
                  :class="a.progress.completed ? 'bg-emerald-500' : 'bg-blue-500'"
                  :style="{ width: `${achievementPercent(a)}%` }"
                />
              </div>
              
              <div v-if="a.type === 'counter' && stepCount(a)" class="mt-3 flex flex-wrap gap-1.5">
                <span
                  v-for="t in stepThresholdsSorted(a)"
                  :key="t"
                  class="rounded-full border px-2 py-0.5 text-[11px]"
                  :class="(a.progress.highest_step ?? 0) >= t
                    ? 'border-emerald-500/60 text-emerald-400'
                    : 'border-slate-600 text-slate-400'"
                  :title="`+${stepPoints(a, t)} Punkte bei ${t}`"
                >
                  {{ t }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .18s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>