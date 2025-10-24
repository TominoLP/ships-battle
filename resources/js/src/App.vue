<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, proxyRefs, ref, watch, watchEffect } from 'vue';
import CreatePanel from '@/src/components/Join/CreatePanel.vue';
import LobbyPanel from '@/src/components/LobbyPanel.vue';
import PlacementBoard from '@/src/components/Placement/PlacementBoard.vue';
import EnemyBoard from '@/src/components/Play/EnemyBoard.vue';
import GameOverModal from '@/src/components/Modals/GameOverModal.vue';
import ShipPalette from '@/src/components/Placement/ShipPalette.vue';
import ActiveShips from '@/src/components/Placement/ActiveShips.vue';
import Statistics from '@/src/components/Stats/Statistics.vue';
import EnemyShips from '@/src/components/Placement/EnemyShips.vue';
import AbilityPanel from '@/src/components/AbilityPanel.vue';
import AuthPanel from '@/src/components/Auth/AuthPanel.vue';
import LeaderboardPanel from '@/src/components/Stats/LeaderboardPanel.vue';

import { useGameState } from '@/src/composables/useGameState';
import { usePlacement } from '@/src/composables/placement';
import { useAbilities } from '@/src/composables/useAbilities';
import { useShipDrag } from '@/src/composables/useShipDrag';
import type { PlacedShip, ShipSpec } from '@/src/types';
import GameControllerRoutes from '@/actions/App/Http/Controllers/GameController';
import { api } from '@/src/composables/useApi';
import { useAuth } from '@/src/composables/useAuth';

const gs = proxyRefs(useGameState());
const auth = useAuth();

const booting = auth.booting;
const isAuthenticated = computed(() => !!auth.user.value);
const accountName = computed(() => auth.user.value?.name ?? '');

// Fleet spec
const fleet: ShipSpec[] = [
  { name: 'Battleship', size: 5, count: 1 },
  { name: 'Cruiser', size: 4, count: 2 },
  { name: 'Destroyer', size: 3, count: 3 },
  { name: 'Submarine', size: 2, count: 4 }
];

const de: Record<number, string> = {
  5: 'Flugzeugträger',
  4: 'Schlachtschiff',
  3: 'Kreuzer',
  2: 'U-Boot'
};

const showMyBoard = ref(false);

watch(() => auth.user.value, (user) => {
  if (!user) {
    gs.resetForNewGame();
  }
});

async function handleLogout() {
  try {
    await auth.logout();
  } catch (err) {
    console.error('[Auth] Logout failed', err);
  }
}

// Placement composable
const placement = usePlacement(12, fleet);
watchEffect(() => {
  gs.myBoard = placement.board.value;
});

const shipDrag = useShipDrag();
const placementBoardRef = ref<any>(null);
const draggingShip = ref<PlacedShip | null>(null);
const originalShip = ref<PlacedShip | null>(null);

// Abilities composable
const abilities = useAbilities(12);

// Ship drag composable
// UI-friendly fleet
const uiFleet = computed(() =>
  fleet.map(f => ({ name: de[f.size] ?? f.name, size: f.size, total: f.count }))
);

// Placed sizes (for palette)
const placedSizes = computed<number[]>(() => {
  const arr: any = (placement as any).placedShips;
  const list = Array.isArray(arr) ? arr : arr?.value ?? [];
  return list.map((s: any) => s.size);
});

// Ability availability (using game state)
const PLANE_TOTAL = 1;
const SPLATTER_TOTAL = 2;
const BOMBS_TOTAL = 1;

const planeRemaining = computed(() =>
  Math.max(0, PLANE_TOTAL - gs.abilityUsage.plane)
);
const splatterRemaining = computed(() =>
  Math.max(0, SPLATTER_TOTAL - gs.abilityUsage.splatter)
);
const bombsRemaining = computed(() =>
  Math.max(0, BOMBS_TOTAL - gs.abilityUsage.comb)
);

const isDisabled = computed(() => !placement.allPlaced.value)

const planeExhausted = computed(() => planeRemaining.value === 0);
const splatterExhausted = computed(() => splatterRemaining.value === 0);
const bombsExhausted = computed(() => bombsRemaining.value === 0);

const canUsePlane = computed(() => gs.myTurn && !planeExhausted.value);
const canUseSplatter = computed(() => gs.myTurn && !splatterExhausted.value);
const canUseBomb = computed(() =>
  gs.myTurn && gs.turnKills >= 2 && !bombsExhausted.value
);

// Popup state
const popup = ref<{ open: boolean; title: string; message: string }>({
  open: false, title: '', message: ''
});

function openPopup(title: string, message: string) {
  popup.value = { open: true, title, message };
}
function closePopup() {
  popup.value.open = false;
}

// Selection (type-based for placing)
const selectedSize = ref<number | null>(null);
function pickSize(size: number | null) {
  selectedSize.value = size;
}

const orientationStr = computed<'H' | 'V'>(() =>
  placement.orientation.value as 'H' | 'V'
);

function commitPlacement(x: number, y: number, size: number, dir: 'H' | 'V') {
  placement.applyShip(x, y, size, dir);
  const totalOfSize = uiFleet.value.find(f => f.size === size)?.total ?? 0;
  const nowPlaced = placedSizes.value.filter(s => s === size).length;
  if (nowPlaced >= totalOfSize) selectedSize.value = null;
}

function startShipDrag({ ship, event }: { ship: PlacedShip; event: PointerEvent }) {
  if (gs.step !== 'placing') return;

  const removed = placement.removeShip(ship);
  if (!removed) return;

  draggingShip.value = removed;
  originalShip.value = { ...removed };
  selectedSize.value = removed.size;
  placement.orientation.value = removed.dir;

  const boardComponent = placementBoardRef.value;
  shipDrag.boardRef.value = boardComponent;

  const startPoint = boardComponent?.getCellFromPoint?.(event.clientX, event.clientY);
  if (startPoint) {
    boardComponent?.setExternalHover?.(startPoint.x, startPoint.y);
  }

  const revertToOriginal = () => {
    if (!originalShip.value) return;
    placement.applyShip(
      originalShip.value?.x,
      originalShip.value?.y,
      originalShip.value.size,
      originalShip.value.dir
    );
    placement.orientation.value = originalShip.value.dir;
    boardComponent?.clearHover?.();
    draggingShip.value = null;
    originalShip.value = null;
    selectedSize.value = null;
    shipDrag.boardRef.value = null;
  };

  shipDrag.startDrag(
    event,
    removed.size,
    removed.dir,
    (x, y) => {
      if (x >= 0 && y >= 0) {
        boardComponent?.setExternalHover?.(x, y);
      } else {
        boardComponent?.setExternalHover?.(null, null);
      }
    },
    (x, y) => {
      const finalDir = placement.orientation.value as 'H' | 'V';
      const size = removed.size;
      const can = placement.canPlace(x, y, size, finalDir);

      if (can) {
        commitPlacement(x, y, size, finalDir);
        shipDrag.boardRef.value = null;
      } else {
        revertToOriginal();
        return;
      }

      draggingShip.value = null;
      originalShip.value = null;
      selectedSize.value = null;
      boardComponent?.clearHover?.();
    },
    revertToOriginal
  );
}

// === KEYBOARD HANDLING ===

function onKeydown(e: KeyboardEvent) {
  // 1) Ability plane rotate while ghosting
  if (gs.step === 'playing' &&
    abilities.ghostActive.value &&
    abilities.activeAbility.value === 'plane' &&
    (e.key === 'r' || e.key === 'R')) {
    e.preventDefault();
    abilities.rotatePlane();
    if (abilities.hoverCell.value) {
      previewCells.value = abilities.computePreview('plane', {
        axis: abilities.planeAxis.value,
        hover: abilities.hoverCell.value
      });
      positionPlaneGhost(abilities.hoverCell.value);
    }
    return;
  }

  // 2) Ship rotate WHILE dragging (placing step)
  if (gs.step === 'placing' && shipDrag.isDragging.value && (e.key === 'r' || e.key === 'R')) {
    e.preventDefault();
    placement.orientation.value = placement.orientation.value === 'H' ? 'V' : 'H';
    const size = selectedSize.value ?? draggingShip.value?.size ?? 0;
    if (size > 0) {
      shipDrag.updateGhost(size, orientationStr.value);
    }
    return;
  }

  // 3) Quick ship selection via number keys
  if (gs.step === 'placing' && !shipDrag.isDragging.value && /^[1-4]$/.test(e.key)) {
    e.preventDefault();
    const idx = Number(e.key) - 1;
    const option = uiFleet.value[idx];
    if (!option) return;
    const alreadyPlaced = placedSizes.value.filter(size => size === option.size).length;
    if (alreadyPlaced < option.total) {
      selectedSize.value = option.size;
    }
    return;
  }

  // 4) Ship rotate during placement (click-based)
  if (gs.step === 'placing' && (e.key === 'r' || e.key === 'R')) {
    e.preventDefault();
    placement.orientation.value = placement.orientation.value === 'H' ? 'V' : 'H';
    return;
  }

  // Undo
  if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
    e.preventDefault();
    placement.removeLast();
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

// === PLAYING PHASE ===

// Compact list for playing
const playingItems = computed(() => {
  const countPerSize: Record<number, number> = {};
  for (const f of fleet) countPerSize[f.size] = f.count;
  return Object.entries(countPerSize).flatMap(([sizeStr, total]) => {
    const size = Number(sizeStr);
    return Array.from({ length: total }, (_, i) => ({
      key: `${size}-${i + 1}`, size
    }));
  });
});

// Status message
const statusMessage = computed(() => {
  switch (gs.step) {
    case 'join': return 'Erstelle ein neues Spiel oder tritt einem Spiel mit Code bei.';
    case 'lobby': return 'Warte in der Lobby, bis beide bereit sind.';
    case 'placing': return 'Platziere deine Schiffe auf dem Spielfeld.';
    case 'playing': return gs.gameOver ? 'Spiel beendet.' : (gs.myTurn ? 'Dein Zug!' : 'Gegner am Zug …');
    default: return '';
  }
});

async function placeRandomly() {
  try {
    const body = gs.playerId ? { player_id: gs.playerId } : undefined;
    const data = await api<{
      board: number[][];
      ships: Array<{ x: number; y: number; size: number; dir: 'H' | 'V' }>;
    }>(
      GameControllerRoutes.randomPlacement.post(),
      body
    );
    if (data?.ships?.length) {
      placement.setShips(data.ships.map(ship => ({
        x: ship.x,
        y: ship.y,
        size: ship.size,
        dir: ship.dir
      })));
    } else {
      placement.randomlyPlaceAll();
    }
  } catch (err) {
    console.error('[Placement] random placement failed, using local fallback', err);
    placement.randomlyPlaceAll();
  } finally {
    selectedSize.value = null;
  }
}

// === ABILITIES ===

const previewCells = ref<Array<{ x: number; y: number }>>([]);
const enemyBoardRef = ref<any>(null);

const boardDisabled = computed(() =>
  gs.gameOver || (!abilities.ghostActive.value && !gs.myTurn)
);

function startAbilityFromPanel(type: 'plane' | 'comb' | 'splatter', ev: PointerEvent) {
  abilities.startGhost(type);
  document.body.style.userSelect = 'none';

  window.addEventListener('pointerup', onAbilityMouseUp, { capture: true, once: true });
  window.addEventListener('mousemove', onAbilityMouseMove, { passive: true });
}

function onAbilityMouseMove(ev: MouseEvent) {
  if (!abilities.ghostElement.value) return;

  const el = abilities.ghostElement.value;
  if (abilities.activeAbility.value === 'plane') {
    el.style.left = `${ev.clientX}px`;
    el.style.top = `${ev.clientY}px`;
  } else {
    const w = el.offsetWidth || 0;
    const h = el.offsetHeight || 0;
    const c = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
    el.style.left = `${ev.clientX - w / 2}px`;
    el.style.top = `${ev.clientY - h / 2}px`;
  }
}

async function onAbilityMouseUp(ev: PointerEvent) {
  if (!abilities.ghostActive.value || !abilities.activeAbility.value) return;

  let pt = getEnemyCellFromEvent(ev);
  if (!pt && abilities.hoverCell.value) pt = abilities.hoverCell.value;

  const finish = () => {
    abilities.destroyGhost();
    previewCells.value = [];
    document.body.style.userSelect = '';
    window.removeEventListener('mousemove', onAbilityMouseMove);
  };

  if (!pt) {
    return finish();
  }

  try {
    if (abilities.activeAbility.value === 'plane') {
      const axis = abilities.planeAxis.value;
      const index = axis === 'row' ? pt.y : pt.x;
      await gs.useAbility('plane', { axis, index });
    } else if (abilities.activeAbility.value === 'comb') {
      await gs.useAbility('comb', { center: pt });
    } else if (abilities.activeAbility.value === 'splatter') {
      await gs.useAbility('splatter', {});
    }
  } catch (err) {
    console.error('[Ability] ERROR', err);
  } finally {
    finish();
  }
}

function onEnemyHover(x: number, y: number) {
  abilities.hoverCell.value = { x, y };

  if (!abilities.ghostActive.value || !abilities.activeAbility.value) {
    previewCells.value = [];
    return;
  }

  if (abilities.activeAbility.value === 'plane') {
    previewCells.value = abilities.computePreview('plane', {
      axis: abilities.planeAxis.value,
      hover: { x, y }
    });
    positionPlaneGhost({ x, y });
  } else if (abilities.activeAbility.value === 'comb') {
    previewCells.value = abilities.computePreview('comb', { center: { x, y } });
  } else {
    previewCells.value = [];
  }
}

const cellPx = computed(() =>
  window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32
);
const gapPx = 6;

function positionPlaneGhost(hover: { x: number; y: number } | null) {
  if (!abilities.ghostElement.value || !hover) return;
  const grid = enemyBoardRef.value?.getGridEl?.() as HTMLElement | null;
  if (!grid) return;

  const rect = grid.getBoundingClientRect();
  const step = cellPx.value + gapPx;

  let left: number, top: number;
  if (abilities.planeAxis.value === 'row') {
    left = rect.left;
    top = rect.top + hover.y * step;
  } else {
    left = rect.left + hover.x * step;
    top = rect.top;
  }
  abilities.ghostElement.value.style.left = `${left}px`;
  abilities.ghostElement.value.style.top = `${top}px`;
}

function getEnemyCellFromEvent(ev: PointerEvent) {
  const byApi = enemyBoardRef.value?.getCellFromPoint?.(ev.clientX, ev.clientY);
  if (byApi) return byApi;
  if (abilities.hoverCell.value) return abilities.hoverCell.value;
  return null;
}

async function onEnemyCellClick(x: number, y: number) {
  if (abilities.ghostActive.value && abilities.activeAbility.value) return;
  if (gs.gameOver) return;
  gs.fire(x, y);
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-6 sm:p-8 text-slate-200">
    <div class="max-w-[1800px] mx-auto">
      <div v-if="booting" class="flex min-h-[60vh] items-center justify-center text-slate-400">
        Anmeldung wird geprüft …
      </div>
      <template v-else>
        <div v-if="!isAuthenticated" class="py-12">
          <AuthPanel />
        </div>
        <template v-else>
          <div class="flex flex-col gap-6">
            <div class="relative mb-6 text-center">
              <div class="absolute right-0 top-0 flex justify-end">
                <div class="inline-flex items-center gap-3 rounded-full border border-slate-700 bg-slate-900/80 px-4 py-2 shadow-md">
                  <span class="text-sm text-slate-300">Angemeldet als <span class="font-semibold">{{ accountName }}</span></span>
                  <button class="text-sm text-red-400 hover:text-red-300" type="button" @click="handleLogout">
                    Abmelden
                  </button>
                </div>
                <a
                  href="https://github.com/TominoLP/ships-battle"
                  target="_blank"
                  rel="noopener noreferrer"
                  title="GitHub: ships-battle"
                  class="inline-flex items-center gap-3 rounded-full border border-slate-700 ml-3 bg-slate-900/80 px-4 py-2 shadow-md hover:bg-slate-800/80 focus:outline-none focus:ring-2 focus:ring-blue-500/60"
                >
                  <i class="fa-brands fa-github"></i>
                  <span class="sr-only">GitHub: ships-battle</span>
                </a>
              </div>

              <div class="flex items-center justify-center gap-3">
                <svg class="h-10 w-10 text-blue-400" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                     stroke-width="2" viewBox="0 0 24 24">
                  <circle cx="12" cy="5" r="2.5" />
                  <path d="M12 8.5v12" />
                  <path d="M7 11h10" />
                  <path d="M12 20.5c-3.5 0-6-2.5-6-5.5" />
                  <path d="M12 20.5c3.5 0 6-2.5 6-5.5" />
                </svg>
                <h1 class="text-3xl font-semibold text-blue-400">Schiffeversenken</h1>
              </div>

              <p class="mt-2 text-slate-400">{{ statusMessage }}</p>
            </div>
          </div>

          <!-- JOIN -->
          <div v-if="gs.step === 'join'" class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-xl border border-slate-700 bg-slate-900/80 p-6 shadow-xl">
              <CreatePanel
                v-model:gameCode="gs.gameCode"
                :userName="accountName"
                @create="gs.createGame"
                @join="gs.joinGame"
              />
            </div>
            <LeaderboardPanel :userName="accountName"/>
          </div>

          <!-- LOBBY -->
          <div v-else-if="gs.step === 'lobby'" class="mx-auto max-w-xl">
            <div class="rounded-xl border border-slate-700 bg-slate-900/80 p-6 shadow-xl">
              <LobbyPanel :gameCode="gs.gameCode" :isReady="gs.isReady" />
            </div>
          </div>

          <!-- PLACING -->
          <section v-else-if="gs.step === 'placing'"
                   class="mx-auto w-max rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl">
            <h3 class="mb-4 text-center text-slate-200">Dein Spielbrett</h3>

            <div class="grid grid-cols-[minmax(220px,280px)_1fr] gap-4">
              <div>
                <ShipPalette
                  :fleet="uiFleet"
                  :orientation="orientationStr"
                  :placedSizes="placedSizes"
                  :selectedSize="selectedSize ?? null"
                  @pickSize="pickSize"
                  @reset="() => { placement.reset(); selectedSize = null }"
                  @toggleOrientation="placement.orientation.value = placement.orientation.value === 'H' ? 'V' : 'H'"
                  @undo="placement.removeLast"
                />

                <button
                  class="group inline-flex w-full items-center justify-center gap-2 rounded-lg border px-3 py-2 mt-2
                         border-slate-600 bg-slate-800 hover:bg-slate-700 text-slate-200"
                  @click="placeRandomly()"
                >
                  <i class="fa-solid fa-random text-sm"></i>
                  <span class="text-xs sm:text-[13px]">Zufällig platzieren</span>
                </button>

                <div class="relative group inline-block w-full">
                  <button
                    :disabled="isDisabled"
                    :aria-disabled="isDisabled.toString()"
                    :aria-describedby="isDisabled ? 'ready-tooltip' : undefined"
                    class="mt-4 w-full rounded-lg border px-3 py-2 transition"
                    :class="isDisabled
        ? 'bg-slate-800 border-slate-600 cursor-not-allowed opacity-80'
        : 'bg-emerald-600 hover:bg-emerald-700 border-emerald-500'"
                    @click="gs.readyUp(placement.placedShips)"
                  >
                    Bereit
                  </button>

                  <!-- Tooltip (shown on wrapper hover when disabled) -->
                  <div
                    v-if="isDisabled"
                    id="ready-tooltip"
                    role="tooltip"
                    class="pointer-events-none absolute left-1/2 -translate-x-1/2 -top-2 -translate-y-full
             hidden group-hover:block whitespace-nowrap rounded-md bg-gray-900 px-2 py-1
             text-xs text-white shadow-lg"
                  >
                    Platziere zuerst alle Schiffe.
                    <span class="absolute left-1/2 top-full -translate-x-1/2 h-2 w-2 rotate-45 bg-gray-900"></span>
                  </div>
                </div>
              </div>

              <div class="rounded-lg border border-slate-700/70 bg-slate-900/60 p-4">
                <PlacementBoard
                  ref="placementBoardRef"
                  :board="placement.board.value"
                  :canPlace="placement.canPlace"
                  :nextSize="selectedSize ?? placement.nextSize.value"
                  :orientation="placement.orientation.value"
                  :applyShip="commitPlacement"
                  :placedShips="placement.placedShips.value"
                  @shipDragStart="startShipDrag"
                />
              </div>
            </div>
          </section>

          <!-- PLAYING -->
          <div v-else class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
            <section v-if="!showMyBoard" class="lg:col-span-1 h-full rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl">
              <Statistics
                :my-board="gs.myBoard"
                :enemy-board="gs.enemyBoard"
                :enemy-name="gs.enemyName"
                :ability-usage="gs.abilityUsage"
                :enemy-sunk-ships="gs.enemySunkShips"
                :step="gs.step"
                :game-over="gs.gameOver"
                :my-turn="gs.myTurn"
              />
            </section>

            <section :class="['rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl', showMyBoard ? 'lg:col-span-2' : 'lg:col-span-2']">
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-slate-200">{{ gs.enemyName }}</h3>
                <div class="text-xs text-slate-500">Fähigkeiten auswählen und ziehen. (R dreht Flugzeug)</div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-1">
                  <EnemyShips :ships="playingItems" :sunk-ships="gs.enemySunkShips" />

                  <AbilityPanel
                    class="mt-4"
                    :canUsePlane="canUsePlane"
                    :canUseBomb="canUseBomb"
                    :canUseSplatter="canUseSplatter"
                    :planeRemaining="planeRemaining"
                    :planeTotal="PLANE_TOTAL"
                    :bombRemaining="bombsRemaining"
                    :bombTotal="BOMBS_TOTAL"
                    :splatterRemaining="splatterRemaining"
                    :splatterTotal="SPLATTER_TOTAL"
                    :planeExhausted="planeExhausted"
                    :bombExhausted="bombsExhausted"
                    :splatterExhausted="splatterExhausted"
                    @startAbility="startAbilityFromPanel"
                    @showError="openPopup"
                  />
                </div>

                <div class="md:col-span-3">
                  <div
                    :class="[
                      'rounded-lg border bg-slate-900/60 p-4 h-full',
                      gs.gameOver && 'pointer-events-none opacity-60',
                      gs.myTurn ? 'border-emerald-500/50' : 'border-rose-500/50'
                    ]"
                  >
                    <EnemyBoard
                      ref="enemyBoardRef"
                      :disabled="boardDisabled"
                      :enemyBoard="gs.enemyBoard"
                      :preview-cells="previewCells"
                      @hover="onEnemyHover"
                      @fire="onEnemyCellClick"
                    />
                  </div>
                </div>
              </div>
            </section>

            <!-- YOUR SHIPS -->
            <section :class="['rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl h-full', showMyBoard ? 'lg:col-span-2' : 'lg:col-span-1']">
              <div class="mb-4 flex items-center justify-between">
                <h3 class="text-slate-200">Deine Schiffe</h3>
                <button
                  class="rounded-lg border border-slate-600 px-3 py-1 text-sm text-slate-200 hover:bg-slate-800"
                  @click="showMyBoard = !showMyBoard"
                >
                  {{ showMyBoard ? 'Eigenes Brett ausblenden' : 'Eigenes Brett anzeigen' }}
                </button>
              </div>

              <div :class="['grid gap-4', showMyBoard ? 'grid-cols-4' : 'grid-cols-1']">
                <div :class="showMyBoard ? 'md:col-span-1' : ''">
                  <ActiveShips :board="gs.myBoard" :ships="playingItems" />
                </div>

                <div v-if="showMyBoard" class="md:col-span-3">
                  <div class="rounded-lg border border-slate-700/70 bg-slate-900/60 p-4 h-full">
                    <PlacementBoard
                      :board="gs.myBoard"
                      :canPlace="() => false"
                      :nextSize="null"
                      :orientation="'H'"
                      :applyShip="() => {}"
                      :placedShips="[]"
                    />
                  </div>
                </div>
              </div>
            </section>
          </div>

          <!-- POPUP -->
          <div
            v-if="popup.open"
            class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/50"
            @click.self="closePopup()"
          >
            <div class="w-full max-w-sm rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl">
              <div class="mb-2 text-lg font-semibold text-slate-100">{{ popup.title }}</div>
              <div class="mb-4 text-sm text-slate-300">{{ popup.message }}</div>
              <button
                class="rounded-md border border-slate-600 px-3 py-1.5 text-slate-200 hover:bg-slate-800"
                @click="closePopup()"
              >
                OK
              </button>
            </div>
          </div>

          <GameOverModal
            :open="gs.gameOver"
            :winnerName="gs.winnerName"
            :youWon="gs.youWon"
            @close="gs.resetForNewGame"
          />
        </template>
      </template>
    </div>
  </div>
</template>
