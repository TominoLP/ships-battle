<script lang="ts" setup>
import { computed, onMounted, onBeforeUnmount, watch, ref } from 'vue';

type LevelEdge = {
  id: number;
  name: string;
  min_points: number;
};

type LevelInfo = {
  points: number;
  current: LevelEdge | null;
  next: (LevelEdge & { points_to_go: number }) | null;
};

type AchievementProgress = {
  value: number;
  highest_step: number | null;
  next_step: number | null;
  remaining: number | null;
  completed: boolean;
  unlocked_at: string | null;
};

type AchievementItem = {
  key: string;
  name: string;
  description?: string | null;
  type: 'counter' | 'event';
  steps: number[];
  progress: AchievementProgress;
};

const props = defineProps<{
  /** Controls modal visibility */
  open: boolean;
  /** Level info from user.level */
  level: LevelInfo | null | undefined;
  /** Achievements from user.achievements */
  achievements: AchievementItem[] | null | undefined;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'close'): void;
}>();

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
  const currentName = lvl.current?.name ?? 'Level';
  return `${currentName}`;
});

function achievementStatus(a: AchievementItem): string {
  if (a.type === 'event') {
    return a.progress.completed ? 'Freigeschaltet' : 'Noch nicht freigeschaltet';
  }
  const next = a.progress.next_step;
  if (a.progress.completed) {
    return `Abgeschlossen (${a.progress.value})`;
  }
  if (next != null) {
    return `${a.progress.value} / ${next}`;
  }
  return `${a.progress.value}`;
}

function achievementPercent(a: AchievementItem): number {
  if (a.type === 'event') {
    return a.progress.completed ? 100 : 0;
  }
  const next = a.progress.next_step ?? (a.steps.length ? a.steps[a.steps.length - 1] : 0);
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
      <div
        class="w-[min(1100px,92vw)] max-h-[88vh] overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between gap-3 border-b border-slate-700/70 px-5 py-4">
          <div class="flex items-center gap-3">
            <i class="fa-solid fa-trophy text-amber-300"></i>
            <h2 class="text-lg font-semibold text-slate-100">
              Fortschritt & Erfolge
            </h2>
          </div>
          <button
            class="rounded-md border border-slate-600 px-3 py-1.5 text-slate-200 hover:bg-slate-800"
            @click="close"
            aria-label="Schließen"
          >
            Schließen
          </button>
        </div>
        
        <div class="overflow-y-auto p-5">
          <div class="rounded-xl border border-slate-700/70 bg-slate-900/70 p-4">
            <div class="mb-2 flex flex-wrap items-end justify-between gap-3">
              <div>
                <div class="text-sm text-slate-400">Aktueller Rang</div>
                <div class="text-xl font-semibold text-slate-100">{{ levelLabel }}</div>
              </div>
              <div class="text-right">
                <div class="text-sm text-slate-400">Gesamtpunkte</div>
                <div class="text-lg font-semibold text-slate-100">
                  {{ props.level?.points ?? 0 }}
                </div>
              </div>
            </div>

            <div class="h-3 w-full overflow-hidden rounded-full border border-slate-700 bg-slate-800">
              <div
                class="h-full rounded-full bg-emerald-500 transition-[width]"
                :style="{ width: `${levelPercent}%` }"
              />
            </div>

            <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
              <div>
                {{ props.level?.current?.name ?? 'Level' }}
                <span v-if="props.level?.current">• ab {{ props.level.current.min_points }} P</span>
              </div>
              <div v-if="props.level?.next">
                Nächstes: {{ props.level.next.name }}
                • {{ props.level.next.points_to_go }} P bis zum Aufstieg
              </div>
              <div v-else class="text-amber-300">Max. Level erreicht</div>
            </div>
          </div>
          
          <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <div class="text-slate-300">Erfolge</div>
            <div class="flex gap-2">
              <button
                class="rounded-md border px-3 py-1.5 text-xs transition"
                :class="filter === 'all'
                  ? 'border-amber-400 text-amber-300 bg-slate-800/60'
                  : 'border-slate-600 text-slate-300 hover:bg-slate-800'"
                @click="filter = 'all'"
              >Alle</button>
              <button
                class="rounded-md border px-3 py-1.5 text-xs transition"
                :class="filter === 'inprogress'
                  ? 'border-amber-400 text-amber-300 bg-slate-800/60'
                  : 'border-slate-600 text-slate-300 hover:bg-slate-800'"
                @click="filter = 'inprogress'"
              >In Arbeit</button>
              <button
                class="rounded-md border px-3 py-1.5 text-xs transition"
                :class="filter === 'done'
                  ? 'border-amber-400 text-amber-300 bg-slate-800/60'
                  : 'border-slate-600 text-slate-300 hover:bg-slate-800'"
                @click="filter = 'done'"
              >Abgeschlossen</button>
            </div>
          </div>
          
          <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
              v-for="a in filteredAchievements"
              :key="a.key"
              class="rounded-xl border border-slate-700 bg-slate-900/70 p-4"
            >
              <div class="mb-2 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <i
                    class="fa-solid"
                    :class="a.type === 'event'
                      ? (a.progress.completed ? 'fa-bolt text-amber-300' : 'fa-bolt text-slate-500')
                      : (a.progress.completed ? 'fa-medal text-amber-300' : 'fa-flag text-slate-400')"
                  />
                  <div class="font-semibold text-slate-100">{{ a.name }}</div>
                </div>
                <span
                  class="rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wide"
                  :class="a.progress.completed
                    ? 'border-emerald-500/60 text-emerald-400'
                    : 'border-slate-600 text-slate-400'"
                >
                  {{ a.type === 'event' ? (a.progress.completed ? 'Freigeschaltet' : 'Event') : 'Stufen' }}
                </span>
              </div>

              <div v-if="a.description" class="mb-3 text-xs text-slate-400">
                {{ a.description }}
              </div>
              
              <div class="mb-2 flex items-center justify-between text-xs text-slate-400">
                <div>{{ achievementStatus(a) }}</div>
                <div v-if="a.type === 'counter' && a.progress.next_step != null">
                  Noch {{ a.progress.remaining }} bis {{ a.progress.next_step }}
                </div>
              </div>

              <div class="h-2 w-full overflow-hidden rounded-full border border-slate-700 bg-slate-800">
                <div
                  class="h-full rounded-full"
                  :class="a.progress.completed ? 'bg-emerald-500' : 'bg-blue-500'"
                  :style="{ width: `${achievementPercent(a)}%` }"
                />
              </div>
              
              <div v-if="a.type === 'counter' && a.steps.length" class="mt-3 flex flex-wrap gap-1.5">
                <span
                  v-for="t in a.steps"
                  :key="t"
                  class="rounded-full border px-2 py-0.5 text-[11px]"
                  :class="(a.progress.highest_step ?? 0) >= t
                    ? 'border-emerald-500/60 text-emerald-400'
                    : 'border-slate-600 text-slate-400'"
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
