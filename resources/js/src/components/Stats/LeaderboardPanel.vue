<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import StatsController from '@/actions/App/Http/Controllers/StatsController';
import { api } from '@/src/composables/useApi';
import type { LeaderboardEntry } from '@/src/types';

type TabKey = 'ships' | 'wins';

const tabs: Array<{ key: TabKey; label: string }> = [
  { key: 'ships', label: 'Meiste zerstörte Schiffe' },
  { key: 'wins', label: 'Meiste Siege' },
];

const activeTab = ref<TabKey>('ships');
const leaderboard = ref<LeaderboardEntry[]>([]);
const loading = ref(false);
const error = ref('');
let requestToken = 0;

const valueTitle = computed(() => {
  switch (activeTab.value) {
    case 'wins': return 'Siege';
    case 'ships':
    default: return 'Zerstörte Schiffe';
  }
});

function displayValue(entry: LeaderboardEntry): string {
  switch (activeTab.value) {
    case 'wins':
      return `${entry.wins}`;
    case 'ships':
    default:
      return `${entry.ships_destroyed}`;
  }
}

async function load(metric: TabKey) {
  const token = ++requestToken;
  loading.value = leaderboard.value.length === 0;
  error.value = '';
  try {
    const data = await api<{ players: LeaderboardEntry[] }>(
      StatsController.leaderboard.get({ metric })
    );
    const list = data?.players ?? [];
    if (token !== requestToken) return;
    leaderboard.value = list;
  } catch (err) {
    if (token !== requestToken) return;
    console.error('[Leaderboard] failed to fetch', err);
    leaderboard.value = [];
    if (err instanceof Error) {
      error.value = err.message;
    } else {
      error.value = 'Leaderboard konnte nicht geladen werden.';
    }
  } finally {
    if (token === requestToken) {
      loading.value = false;
    }
  }
}

watch(activeTab, (metric) => {
  void load(metric);
}, { immediate: true });

const refreshHandler = () => {
  void load(activeTab.value);
};

onMounted(() => {
  if (typeof window === 'undefined') return;
  window.addEventListener('ships-battle:leaderboard:refresh', refreshHandler);
});

onBeforeUnmount(() => {
  if (typeof window === 'undefined') return;
  window.removeEventListener('ships-battle:leaderboard:refresh', refreshHandler);
});
</script>

<template>
  <div class="rounded-xl border border-slate-700/70 bg-slate-900/70 shadow-lg overflow-hidden">
    <!-- Header -->
    <div class="flex flex-col gap-3 border-b border-slate-700/60 px-4 py-4 sm:flex-row sm:items-center">
      <h2 class="text-base font-semibold text-slate-100 flex-1">Leaderboard</h2>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          :class="[
            'rounded-full px-3 py-1 text-xs font-medium transition-colors border',
            tab.key === activeTab
              ? 'bg-blue-600 text-white border-blue-500 shadow'
              : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-800/70'
          ]"
          @click="activeTab = tab.key"
          type="button"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="px-4 py-4 text-sm text-slate-200">
      <Transition name="fade-slide" mode="out-in">
        <div :key="`${activeTab}-${loading}-${error}-${leaderboard.length}`">
          <div v-if="loading" class="py-6 text-center text-slate-400">Lade Rangliste …</div>
          <div v-else-if="error" class="py-6 text-center text-rose-300">{{ error }}</div>
          <div v-else-if="leaderboard.length === 0" class="py-6 text-center text-slate-400">Noch keine abgeschlossenen Spiele.</div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse">
              <thead class="text-slate-400 uppercase tracking-wide border-b border-slate-700/60">
              <tr>
                <th class="py-2 px-3 text-center w-8">#</th>
                <th class="py-2 px-3">Spieler</th>
                <th class="py-2 px-3">{{ valueTitle }}</th>
                <th v-if="activeTab === 'ships'" class="py-2 px-3">Ø / Spiel</th>
                <th v-if="activeTab !== 'ships'"  class="py-2 px-3">Winrate</th>
                <th class="py-2 px-3 text-right">Spiele</th>
              </tr>
              </thead>

              <TransitionGroup name="fade-slide" tag="tbody" class="divide-y divide-slate-700/50">
                <tr
                  v-for="(entry, idx) in leaderboard"
                  :key="`${activeTab}-${entry.user_id}`"
                  :class="[
                    idx === 0 ? 'text-amber-300 font-bold' :
                    idx === 1 ? 'text-slate-200 font-semibold' :
                    idx === 2 ? 'text-amber-200 font-semibold' :
                    'text-slate-300',
                    'hover:bg-slate-700/30 transition-colors'
                  ]"
                >
                  <td class="py-2 px-3 text-center">{{ idx + 1 }}</td>
                  <td class="py-2 px-3 font-medium">{{ entry.name }}</td>
                  <td class="py-2 px-3">{{ displayValue(entry) }}</td>
                  <td v-if="activeTab === 'ships'" class="py-2 px-3">
                    {{ entry.games > 0 ? (entry.ships_destroyed / entry.games).toFixed(1) : '0.0' }}
                  </td>
                  <td v-if="activeTab !== 'ships'" class="py-2 px-3">{{ entry.win_rate.toFixed(1) }} %</td>
                  <td class="py-2 px-3 text-right">{{ entry.games }}</td>
                </tr>
              </TransitionGroup>
            </table>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
.fade-slide-move {
  transition: transform 0.25s ease;
}
</style>
