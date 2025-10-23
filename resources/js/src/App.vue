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

import { useGameState } from '@/src/composables/useGameState';
import { usePlacement } from '@/src/composables/placement';
import type { ShipSpec } from '@/src/types';
import EnemyShips from '@/src/components/Placement/EnemyShips.vue';

const gs = proxyRefs(useGameState());

// Fleet spec
const fleet: ShipSpec[] = [
  { name: 'Battleship', size: 5, count: 1 },
  { name: 'Cruiser', size: 4, count: 2 },
  { name: 'Destroyer', size: 3, count: 3 },
  { name: 'Submarine', size: 2, count: 4 }
];

const PLANE_TOTAL = 1;
const SPLATTER_TOTAL = 2;
const BOMBS_TOTAL = 1;

const de: Record<number, string> = { 5: 'Flugzeugträger', 4: 'Schlachtschiff', 3: 'Kreuzer', 2: 'U-Boot' };

const showMyBoard = ref(false);

// PWA state
const placement = usePlacement(12, fleet);
watchEffect(() => {
  gs.myBoard = placement.board.value;
});

// UI-friendly fleet for ShipPalette (type-based)
const uiFleet = computed(() =>
  fleet.map(f => ({ name: de[f.size] ?? f.name, size: f.size, total: f.count }))
);

const planeUsedCount = ref(0);
const splatterUsedCount = ref(0);
const bombUsedCount = ref(0);
const sunkAtTurnStart = ref(0);

const lastGameCode = ref<string | null>(null);

watchEffect(() => {
  const code = (gs as any).gameCode ?? null;
  if (code && code !== lastGameCode.value) {
    planeUsedCount.value = 0;
    splatterUsedCount.value = 0;
    lastGameCode.value = code;
  }
});

const turnKillss = computed(() => sunkAtTurnStart.value - baselineAtTurnStart.value)

const planeRemaining = computed(() => Math.max(0, PLANE_TOTAL - planeUsedCount.value));
const splatterRemaining = computed(() => Math.max(0, SPLATTER_TOTAL - splatterUsedCount.value));
const bombsRemaining = computed(() => Math.max(0, BOMBS_TOTAL - bombUsedCount.value));
const planeExhausted = computed(() => planeRemaining.value === 0);
const splatterExhausted = computed(() => splatterRemaining.value === 0);
const bombsExhausted = computed(() => bombsRemaining.value === 0);


const canUsePlane = computed(() => gs.myTurn && !planeExhausted.value);
const canUseSplatter = computed(() => gs.myTurn && !splatterExhausted.value);
const canUseBomb = computed(() => gs.myTurn && turnKillss.value >= 2 && !bombsExhausted.value);

const currentSunkCount = computed(() => {
  const v = (gs as any).enemySunkShips;
  return Array.isArray(v) ? v.length : (typeof v === 'number' ? v : 0);
});
const turnKills = ref(0);
const baselineAtTurnStart = ref(0);





// placed sizes (for palette + compact list)
const placedSizes = computed<number[]>(() => {
  const arr: any = (placement as any).placedShips;
  const list = Array.isArray(arr) ? arr : arr?.value ?? [];
  return list.map((s: any) => s.size);
});

const popup = ref<{ open: boolean; title: string; message: string }>({
  open: false, title: '', message: ''
});

function openPopup(title: string, message: string) {
  popup.value = { open: true, title, message };
}
function closePopup() { popup.value.open = false; }

watchEffect(() => {
  if (gs.myTurn) sunkAtTurnStart.value = currentSunkCount.value;
});

const orientationStr = computed<'H' | 'V'>(() => placement.orientation.value as 'H' | 'V');

// SELECTION (type-based for placing)
const selectedSize = ref<number | null>(null);
function pickSize(size: number | null) { selectedSize.value = size; }

// ------- Ship placing drag ghost (existing) -------
const boardRef = ref<any>(null);
const isDragging = ref(false);
const dragGhostEl = ref<HTMLElement | null>(null);

function buildShipGhost(size: number, dir: 'H' | 'V') {
  const cell = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
  const gap = 6;
  const cols = dir === 'H' ? size : 1, rows = dir === 'V' ? size : 1;
  const host = document.createElement('div');
  host.className = 'drag-ghost pointer-events-none';
  Object.assign(host.style, {
    position: 'fixed',
    width: `${cols * cell + (cols - 1) * gap}px`,
    height: `${rows * cell + (rows - 1) * gap}px`
  });
  const grid = document.createElement('div');
  Object.assign(grid.style, {
    display: 'grid',
    gridTemplateColumns: `repeat(${cols},${cell}px)`,
    gridTemplateRows: `repeat(${rows},${cell}px)`,
    gap: `${gap}px`
  });
  for (let i = 0; i < size; i++) {
    const sq = document.createElement('div');
    sq.className = 'rounded-[10px] bg-blue-400/25';
    sq.style.width = `${cell}px`;
    sq.style.height = `${cell}px`;
    grid.appendChild(sq);
  }
  host.appendChild(grid);
  document.body.appendChild(host);
  return host;
}

function destroyGhost() {
  const el = dragGhostEl.value as HTMLElement | null;
  if (el && el.parentNode) el.parentNode.removeChild(el);
  dragGhostEl.value = null;
}

function moveGhost(ev: MouseEvent) {
  const el = dragGhostEl.value as HTMLElement | null;
  if (!el) return;
  const c = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
  el.style.left = `${ev.clientX - c / 2}px`;
  el.style.top = `${ev.clientY - c / 2}px`;
}

function startPointerDrag(ev: PointerEvent) {
  if (!selectedSize.value) return;

  // ⬇️ Release pointer capture from the button and blur it
  const tgt = ev.currentTarget as HTMLElement | null;
  try { (tgt as any)?.releasePointerCapture?.(ev.pointerId); } catch {}
  tgt?.blur?.();

  destroyGhost();
  dragGhostEl.value = buildShipGhost(selectedSize.value!, orientationStr.value);
  Object.assign(dragGhostEl.value!.style, { left: '-9999px', top: '-9999px', zIndex: '9999', position: 'fixed' });

  isDragging.value = true;
  document.body.style.userSelect = 'none';
  lastMouse.value = { x: ev.clientX, y: ev.clientY };
  moveGhostAt(ev.clientX, ev.clientY);

  // 🔁 use pointer events only
  window.addEventListener('pointermove', onPointerMove, { passive: false });
  window.addEventListener('pointerup', onPointerUp, { passive: false, once: true });

  // ❌ remove per-drag keydown listener – rely on global onKeydown only
}

function onPointerMove(ev: PointerEvent) {
  if (!isDragging.value) return;
  lastMouse.value = { x: ev.clientX, y: ev.clientY };
  moveGhostAt(ev.clientX, ev.clientY);

  const pt = boardRef.value?.getCellFromPoint?.(ev.clientX, ev.clientY);
  if (pt) boardRef.value?.setExternalHover?.(pt.x, pt.y);
  else boardRef.value?.setExternalHover?.(null, null);
}

function onPointerUp(ev: PointerEvent) {
  if (!isDragging.value) return;
  const hover = boardRef.value?.getHoverCell?.();
  const pt = hover ?? boardRef.value?.getCellFromPoint?.(ev.clientX, ev.clientY);
  if (pt && selectedSize.value) {
    const size = selectedSize.value, dir = orientationStr.value;
    if (placement.canPlace(pt.x, pt.y, size, dir)) {
      placement.applyShip(pt.x, pt.y, size, dir);
      const totalOfSize = uiFleet.value.find(f => f.size === size)?.total ?? 0;
      const nowPlaced = placedSizes.value.filter(s => s === size).length;
      if (nowPlaced + 1 >= totalOfSize) selectedSize.value = null;
    }
  }
  cleanupDrag();
}
function onMove(ev: MouseEvent) {
  if (!isDragging.value) return;
  moveGhost(ev);
  const pt = boardRef.value?.getCellFromPoint?.(ev.clientX, ev.clientY);
  if (pt) boardRef.value?.setExternalHover?.(pt.x, pt.y);
  else boardRef.value?.setExternalHover?.(null, null);
}

function onUp(ev: MouseEvent) {
  if (!isDragging.value) return;
  const hover = boardRef.value?.getHoverCell?.();
  const pt = hover ?? boardRef.value?.getCellFromPoint?.(ev.clientX, ev.clientY);
  if (pt && selectedSize.value) {
    const size = selectedSize.value, dir = orientationStr.value;
    if (placement.canPlace(pt.x, pt.y, size, dir)) {
      placement.applyShip(pt.x, pt.y, size, dir);
      const totalOfSize = uiFleet.value.find(f => f.size === size)?.total ?? 0;
      const nowPlaced = placedSizes.value.filter(s => s === size).length;
      if (nowPlaced + 1 >= totalOfSize) selectedSize.value = null;
    }
  }
  cleanupDrag();
}

function onKeyDuringDrag(e: KeyboardEvent) {
  if (!isDragging.value) return;

  if (e.key === 'r' || e.key === 'R') {
    e.preventDefault();
    placement.orientation.value = placement.orientation.value === 'H' ? 'V' : 'H';
    if (selectedSize.value) {
      destroyGhost();
      dragGhostEl.value = buildShipGhost(selectedSize.value, orientationStr.value);
      Object.assign(dragGhostEl.value!.style, { zIndex: '9999', position: 'fixed' });
      moveGhostAt(lastMouse.value.x, lastMouse.value.y);   // <— keep ghost under cursor
    }
    return;
  }

  if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
    e.preventDefault();
    placement.removeLast();
  }
}

const lastMouse = ref<{ x: number; y: number }>({ x: 0, y: 0 });

function moveGhostAt(x: number, y: number) {
  const el = dragGhostEl.value;
  if (!el) return;
  const c = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
  el.style.left = `${x - c / 2}px`;
  el.style.top  = `${y - c / 2}px`;
}
function cleanupDrag() {
  isDragging.value = false;
  document.body.style.userSelect = '';
  boardRef.value?.setExternalHover?.(null, null);
  window.removeEventListener('pointermove', onPointerMove);
  destroyGhost();
}

// Keyboard (non-drag)
function onKeydown(e: KeyboardEvent) {
  // 1) Ability plane rotate while ghosting (only during playing)
  if (gs.step === 'playing' &&
    abilityGhostActive.value &&
    activeAbility.value === 'plane' &&
    (e.key === 'r' || e.key === 'R')) {
    e.preventDefault();
    abilityPlaneAxis.value = abilityPlaneAxis.value === 'row' ? 'col' : 'row';
    rebuildAbilityGhost();
    if (ghostHoverCell.value) {
      previewCells.value = computeAbilityPreview('plane', {
        axis: abilityPlaneAxis.value,
        hover: ghostHoverCell.value
      });
      // keep the plane ghost snapped to the line start
      positionPlaneGhostAt(ghostHoverCell.value);
    }
    return;
  }

  // 2) Ship rotate WHILE dragging (placing step)
  if (gs.step === 'placing' && isDragging.value && (e.key === 'r' || e.key === 'R')) {
    e.preventDefault();
    placement.orientation.value = placement.orientation.value === 'H' ? 'V' : 'H';
    if (selectedSize.value) {
      destroyGhost();
      dragGhostEl.value = buildShipGhost(selectedSize.value, orientationStr.value);
      Object.assign(dragGhostEl.value!.style, { zIndex: '9999', position: 'fixed' });
      moveGhostAt(lastMouse.value.x, lastMouse.value.y); // <- keep under cursor
    }
    return;
  }

  // 3) Ship rotate when NOT dragging (placing step)
  if (gs.step === 'placing' && !isDragging.value && (e.key === 'r' || e.key === 'R')) {
    e.preventDefault();
    placement.orientation.value = placement.orientation.value === 'H' ? 'V' : 'H';
    return;
  }

  // Undo as before
  if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
    e.preventDefault();
    placement.removeLast();
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

// compact list for playing (no names)
const playingItems = computed(() => {
  const countPerSize: Record<number, number> = {};
  for (const f of fleet) countPerSize[f.size] = f.count;
  return Object.entries(countPerSize).flatMap(([sizeStr, total]) => {
    const size = Number(sizeStr);
    return Array.from({ length: total }, (_, i) => ({ key: `${size}-${i + 1}`, size }));
  });
});

// status text
const statusMessage = computed(() => {
  switch (gs.step) {
    case 'join': return 'Gib deinen Namen ein und erstelle oder trete einem Spiel bei.';
    case 'lobby': return 'Warte in der Lobby, bis beide bereit sind.';
    case 'placing': return 'Platziere deine Schiffe auf dem Spielfeld.';
    case 'playing': return gs.gameOver ? 'Spiel beendet.' : (gs.myTurn ? 'Dein Zug!' : 'Gegner am Zug …');
    default: return '';
  }
});

function placeRandomly() { placement.randomlyPlaceAll(); selectedSize.value = null; }

/* ===============================
   ABILITIES moved under EnemyShips
   with drag-to-ghost interaction
   =============================== */

const abilitiesOpen = ref(false); // no modal use anymore; kept if other parts rely on it

type AbilityType = 'plane' | 'comb' | 'splatter';
const activeAbility = ref<AbilityType | null>(null);

// plane axis while ghosting (R to rotate)
const abilityPlaneAxis = ref<'row' | 'col'>('row');

// ghost state for abilities
const abilityGhostActive = ref(false);
const abilityGhostEl = ref<HTMLElement | null>(null);
const ghostHoverCell = ref<{ x: number; y: number } | null>(null);

// preview overlay on board (cells affected at current hover)
const previewCells = ref<Array<{ x: number; y: number }>>([]);

const boardDisabled = computed(() =>
  gs.gameOver || (!abilityGhostActive.value && !gs.myTurn)
);

// New: start ability from the panel under EnemyShips
function startAbilityFromPanel(type: AbilityType, ev: MouseEvent | PointerEvent) {
  if (!gs.myTurn) {
    openPopup('Nicht dein Zug', 'Du kannst Fähigkeiten nur in deinem Zug verwenden.');
    return;
  }
  if (type === 'plane' && planeExhausted.value) {
    openPopup('Flugzeug verbraucht', 'Das Flugzeug kann nur einmal pro Spiel eingesetzt werden.');
    return;
  }
  if (type === 'splatter' && splatterExhausted.value) {
    openPopup('Streuschuss verbraucht', 'Der Streuschuss kann nur zweimal pro Spiel eingesetzt werden.');
    return;
  }
  if (type === 'comb' && !canUseBomb.value) {
    openPopup('Bombe gesperrt', 'Die Bombe wird erst freigeschaltet, wenn du in diesem Zug 2 Schiffe versenkt hast.');
    return;
  }

  activeAbility.value = type;
  startAbilityGhost();

  document.body.style.userSelect = 'none';
  window.addEventListener('pointerup', onAbilityMouseUp as any, { capture: true, once: true });
  window.addEventListener('mousemove', onAbilityMouseMove, { passive: true, once: true });
  window.addEventListener('mousemove', onAbilityMouseMove, { passive: true });
}
// Build/destroy/move the ability ghost DOM

// add near the other handlers
async function onAbilityMouseUp(ev: MouseEvent | PointerEvent) {

  if (!abilityGhostActive.value || !activeAbility.value) return;

  let pt = getEnemyCellFromEvent(ev);
  if (!pt && ghostHoverCell.value) pt = ghostHoverCell.value;

  const finish = () => {
    destroyAbilityGhost();
    activeAbility.value = null;
    previewCells.value = [];
    document.body.style.userSelect = '';
    window.removeEventListener('pointerup', onAbilityMouseUp as any, true);
    window.removeEventListener('mouseup', onAbilityMouseUp as any, true);
  };

  if (!pt) {
    return finish();
  }

  try {
    if (activeAbility.value === 'plane') {
      const axis = abilityPlaneAxis.value;
      const index = axis === 'row' ? pt.y : pt.x;
      previewCells.value = computeAbilityPreview('plane', { axis, hover: pt });
      if (typeof (gs as any).useAbility === 'function') {
        await (gs as any).useAbility('plane', { axis, index });
      } else {
        for (const c of computeAbilityCells('plane', { axis, index })) await gs.fire(c.x, c.y);
      }
      planeUsedCount.value += 1;
    } else if (activeAbility.value === 'comb') {
      previewCells.value = computeAbilityPreview('comb', { center: pt });
      if (typeof (gs as any).useAbility === 'function') {
        await (gs as any).useAbility('comb', { center: pt });
      } else {
        for (const c of computeAbilityCells('comb', { center: pt })) await gs.fire(c.x, c.y);
      }
      bombUsedCount.value += 1;
      
    } else if (activeAbility.value === 'splatter') {
      if (typeof (gs as any).useAbility === 'function') {
        await (gs as any).useAbility('splatter', {});
      } else {
        for (const c of computeAbilityCells('splatter', {})) await gs.fire(c.x, c.y);
      }
      splatterUsedCount.value += 1;
    }
  } catch (err) {
    console.error('[Ability] apply ERROR', err);
  } finally {
    finish();
  }
}


// tiny helper so logs aren't spammed on move
let rafMove = 0;
function withRAF(cb: () => void) {
  if (rafMove) return;
  rafMove = requestAnimationFrame(() => { rafMove = 0; cb(); });
}


function startAbilityGhost() {
  destroyAbilityGhost();
  abilityGhostActive.value = true;

  const axis = abilityPlaneAxis.value;

  if (activeAbility.value === 'plane') {
    abilityGhostEl.value = buildPlaneGhost(axis);
  } else if (activeAbility.value === 'comb') {
    abilityGhostEl.value = buildCombGhost();
  } else if (activeAbility.value === 'splatter') {
    // small circular cluster indicator
    abilityGhostEl.value = buildSplatterGhost();
  }

  // place offscreen initially
  Object.assign(abilityGhostEl.value!.style, { left: '-9999px', top: '-9999px', zIndex: '9999' });

  window.addEventListener('mousemove', onAbilityMouseMove, { passive: true });
}

function rebuildAbilityGhost() {
  if (!abilityGhostActive.value) return;
  const old = abilityGhostEl.value;
  if (old && old.parentNode) old.parentNode.removeChild(old);
  if (activeAbility.value === 'plane') {
    abilityGhostEl.value = buildPlaneGhost(abilityPlaneAxis.value);
  } else if (activeAbility.value === 'comb') {
    abilityGhostEl.value = buildCombGhost();
  } else if (activeAbility.value === 'splatter') {
    abilityGhostEl.value = buildSplatterGhost();
  }
  Object.assign(abilityGhostEl.value!.style, { zIndex: '9999', position: 'fixed' });
}

function destroyAbilityGhost() {
  window.removeEventListener('pointermove', onAbilityMouseMove as any);
  window.removeEventListener('pointerup', onAbilityMouseUp as any, true);
  const el = abilityGhostEl.value;
  if (el && el.parentNode) el.parentNode.removeChild(el);
  abilityGhostEl.value = null;
  abilityGhostActive.value = false;
}
function onAbilityMouseMove(ev: MouseEvent) {
  withRAF(() => {
    const el = abilityGhostEl.value;
    if (!el) return;

    if (activeAbility.value === 'plane') {
      el.style.left = `${ev.clientX}px`;
      el.style.top  = `${ev.clientY}px`;
    } else {
      const w = el.offsetWidth || 0;
      const h = el.offsetHeight || 0;
      const c = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
      const left = (w > 0 ? ev.clientX - w / 2 : ev.clientX - c / 2);
      const top  = (h > 0 ? ev.clientY - h / 2 : ev.clientY - c / 2);
      el.style.left = `${left}px`;
      el.style.top  = `${top}px`;
    }
  });
}
// Build ghost shapes (same visual language as ship ghost)
function buildPlaneGhost(axis: 'row' | 'col') {
  const size = 12;
  const cell = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
  const gap = 6;
  const cols = axis === 'row' ? size : 1;
  const rows = axis === 'col' ? size : 1;

  const host = document.createElement('div');
  host.className = 'ability-ghost pointer-events-none';
  Object.assign(host.style, {
    position: 'fixed',         // important: no translate(-50%, -50%)
    width: `${cols * cell + (cols - 1) * gap}px`,
    height: `${rows * cell + (rows - 1) * gap}px`
  });

  const grid = document.createElement('div');
  Object.assign(grid.style, {
    display: 'grid',
    gridTemplateColumns: `repeat(${cols},${cell}px)`,
    gridTemplateRows: `repeat(${rows},${cell}px)`,
    gap: `${gap}px`
  });

  const count = axis === 'row' ? cols : rows;
  for (let i = 0; i < count; i++) {
    const sq = document.createElement('div');
    sq.className = 'rounded-[10px] bg-blue-400/25';
    sq.style.width = `${cell}px`;
    sq.style.height = `${cell}px`;
    grid.appendChild(sq);
  }
  host.appendChild(grid);
  document.body.appendChild(host);
  return host;
}

function buildCombGhost() {
  const cell = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
  const gap = 6;
  const cols = 5, rows = 5;

  const host = document.createElement('div');
  host.className = 'ability-ghost pointer-events-none';
  Object.assign(host.style, {
    position: 'fixed',
    width: `${cols * cell + (cols - 1) * gap}px`,
    height: `${rows * cell + (rows - 1) * gap}px`
  });

  const grid = document.createElement('div');
  Object.assign(grid.style, {
    display: 'grid',
    gridTemplateColumns: `repeat(${cols},${cell}px)`,
    gridTemplateRows: `repeat(${rows},${cell}px)`,
    gap: `${gap}px`
  });

  for (let j = 0; j < rows; j++) {
    for (let i = 0; i < cols; i++) {
      // skip 4 corners
      if ((i === 0 || i === 4) && (j === 0 || j === 4)) {
        const empty = document.createElement('div');
        empty.style.width = `${cell}px`;
        empty.style.height = `${cell}px`;
        grid.appendChild(empty);
        continue;
      }
      const sq = document.createElement('div');
      sq.className = 'rounded-[10px] bg-blue-400/25';
      sq.style.width = `${cell}px`;
      sq.style.height = `${cell}px`;
      grid.appendChild(sq);
    }
  }

  host.appendChild(grid);
  document.body.appendChild(host);
  return host;
}

function buildSplatterGhost() {
  const cell = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
  const host = document.createElement('div');
  host.className = 'ability-ghost pointer-events-none';
  Object.assign(host.style, { position: 'fixed' });
  const dot = document.createElement('div');
  Object.assign(dot.style, {
    width: `${cell}px`, height: `${cell}px`,
    borderRadius: '10px',
    background: 'rgba(96,165,250,.25)' // blue-400/25
  });
  host.appendChild(dot);
  document.body.appendChild(host);
  return host;
}

// EnemyBoard hover → compute preview cells
function onEnemyHover(x: number, y: number) {
  ghostHoverCell.value = { x, y };

  if (!abilityGhostActive.value || !activeAbility.value) {
    previewCells.value = [];
    return;
  }

  if (activeAbility.value === 'plane') {
    previewCells.value = computeAbilityPreview('plane', { axis: abilityPlaneAxis.value, hover: { x, y } });
    positionPlaneGhostAt({ x, y });
  } else if (activeAbility.value === 'comb') {
    previewCells.value = computeAbilityPreview('comb', { center: { x, y } });
  } else if (activeAbility.value === 'splatter') {
    previewCells.value = [];
  }
}

const enemyBoardRef = ref<any>(null);
const cellPx = computed(() => (window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32));
const gapPx = 6;

function positionPlaneGhostAt(hover: { x: number; y: number } | null) {
  if (!abilityGhostEl.value || !hover) return;
  const grid = enemyBoardRef.value?.getGridEl?.() as HTMLElement | null;
  if (!grid) return;

  const rect = grid.getBoundingClientRect();
  const step = cellPx.value + gapPx;

  let left: number, top: number;
  if (abilityPlaneAxis.value === 'row') {
    left = rect.left;
    top  = rect.top + hover.y * step;
  } else {
    left = rect.left + hover.x * step;
    top  = rect.top;
  }
  abilityGhostEl.value.style.left = `${left}px`;
  abilityGhostEl.value.style.top  = `${top}px`;
  
}



function getEnemyCellFromEvent(ev: MouseEvent | PointerEvent) {
  const x = (ev as PointerEvent).clientX, y = (ev as PointerEvent).clientY;

  const byApi = enemyBoardRef.value?.getCellFromPoint?.(x, y);
  if (byApi) return byApi;

  // fallback to last hover (should now be set)
  if (ghostHoverCell.value) return ghostHoverCell.value;

  // (optional) your old math as last resort…
  return null;
}



// EnemyBoard click: apply ghost ability OR normal fire
async function onEnemyCellClick(x: number, y: number) {
  if (abilityGhostActive.value && activeAbility.value) return; // drop handles it
  if (gs.gameOver) return;
  gs.fire(x, y);
}

/* ===== ability helpers ===== */

function computeAbilityPreview(
  type: 'plane' | 'comb',
  payload: any
): Array<{ x: number; y: number }> {
  const size = 12;

  if (type === 'plane') {
    const axis: 'row' | 'col' = payload.axis;
    const hov = payload.hover as { x: number; y: number };
    if (!hov) return [];
    const idx = axis === 'row' ? hov.y : hov.x;
    if (axis === 'row') return Array.from({ length: size }, (_, x) => ({ x, y: idx }));
    return Array.from({ length: size }, (_, y) => ({ x: idx, y }));
  }

  const c = payload.center as { x: number; y: number } | null;
  if (!c) return [];
  const cells: Array<{ x: number; y: number }> = [];
  for (let dy = -2; dy <= 2; dy++) {
    for (let dx = -2; dx <= 2; dx++) {
      if (Math.abs(dx) === 2 && Math.abs(dy) === 2) continue; // skip corners
      const x = c.x + dx, y = c.y + dy;
      if (x >= 0 && x < size && y >= 0 && y < size) cells.push({ x, y });
    }
  }
  return cells;
}

function computeAbilityCells(
  type: AbilityType,
  payload: any
): Array<{ x: number; y: number }> {
  const size = 12;

  if (type === 'plane') {
    const axis: 'row' | 'col' = payload.axis;
    const idx: number = Math.max(0, Math.min(size - 1, payload.index ?? 0));
    if (axis === 'row') return Array.from({ length: size }, (_, x) => ({ x, y: idx }));
    return Array.from({ length: size }, (_, y) => ({ x: idx, y }));
  }

  if (type === 'comb') {
    const c = payload.center as { x: number; y: number } | null;
    if (!c) return [];
    const cells: Array<{ x: number; y: number }> = [];
    for (let dy = -2; dy <= 2; dy++) {
      for (let dx = -2; dx <= 2; dx++) {
        if (Math.abs(dx) === 2 && Math.abs(dy) === 2) continue;
        const x = c.x + dx, y = c.y + dy;
        if (x >= 0 && x < size && y >= 0 && y < size) cells.push({ x, y });
      }
    }
    return cells;
  }

  // splatter: 12 random cells
  const total = size * size;
  const picks = 12;
  const chosen = new Set<number>();
  while (chosen.size < Math.min(picks, total)) {
    chosen.add(Math.floor(Math.random() * total));
  }
  return Array.from(chosen).map(n => ({ x: n % size, y: Math.floor(n / size) }));
}


watch(currentSunkCount, (now, prev) => {
  if (!gs.myTurn) return;
  if (now > prev) {
    turnKills.value += (now - prev);
  }
});

// But make sure to reset turnKills when turn starts
watch(() => gs.myTurn, (now, prev) => {
  if (now && !prev) {
    baselineAtTurnStart.value = currentSunkCount.value;
    turnKills.value = 0;  // This should reset it
  }
}, { immediate: true });
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-6 sm:p-8 text-slate-200">
    <div class="max-w-[1800px] mx-auto">
      <!-- Header -->
      <div class="mb-8 text-center">
        <div class="mb-2 flex items-center justify-center gap-3">
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
        <p class="text-slate-400">{{ statusMessage }}</p>
      </div>

      <!-- JOIN / LOBBY -->
      <div v-if="gs.step === 'join'" class="mx-auto max-w-md">
        <div class="rounded-xl border border-slate-700 bg-slate-900/80 p-6 shadow-xl">
          <CreatePanel v-model:gameCode="gs.gameCode" v-model:name="gs.name" @create="gs.createGame"
                       @join="gs.joinGame" />
        </div>
      </div>

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

            <div class="flex flex-col gap-2 mt-2 mb-2">
              <button
                class="group inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2
                     border-slate-600 bg-slate-800 hover:bg-slate-700 text-slate-200"
                title="Drehen (R)"
                @click="placeRandomly()"
              >
                <i class="fa-solid fa-random text-sm"></i>
                <span class="text-xs sm:text-[13px]">Zufällig platzieren</span>
              </button>
            </div>
            <button
              v-if="!placement.allPlaced.value"
              :disabled="!selectedSize"
              :title="selectedSize ? 'Ziehen, dann R zum Drehen' : 'Wähle zuerst einen Schiffstyp'"
              class="mt-4 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 transition-colors hover:bg-slate-700 disabled:opacity-50"
              @pointerdown.prevent="startPointerDrag"
            >
              <span class="flex items-center justify-center gap-[6px] my-2">
                <span
                  v-for="i in selectedSize || 0"
                  :key="i"
                  class="h-8 w-8 sm:h-9 sm:w-9 rounded-[10px] bg-blue-400/25" />
              </span>
              Ziehe das nächste Schiff {{}}
            </button>

            <button
              v-else
              class="mt-4 w-full rounded-lg border bg-emerald-600 hover:bg-emerald-20000 border-b-emerald-500 px-3 py-2 transition-colors  disabled:opacity-50"
              @click="gs.readyUp(placement.placedShips)"
            >
              Bereit
            </button>
          </div>

          <div class="rounded-lg border border-slate-700/70 bg-slate-900/60 p-4">
            <PlacementBoard
              ref="boardRef"
              :applyShip="(x:number,y:number,s:number,d:'H'|'V')=>{ placement.applyShip(x,y,s,d); }"
              :board="placement.board.value"
              :canPlace="placement.canPlace"
              :nextSize="selectedSize ?? placement.nextSize.value"
              :orientation="placement.orientation.value"
            />
          </div>
        </div>
      </section>

      <!-- PLAYING -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <!-- ENEMY sidebar -->
        <section
          v-if="!showMyBoard"
          class="lg:col-span-1 h-full rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl"
        >
          <Statistics />
        </section>

        <section
          :class="[
            'rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl',
            showMyBoard ? 'lg:col-span-2' : 'lg:col-span-2'
          ]"
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-slate-200">{{ gs.enemyName }}</h3>
            <!-- removed modal button; hint remains -->
            <div class="text-xs text-slate-500">Fähigkeiten unten auswählen und aufs Grid ziehen. (R dreht Flugzeug)</div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-1">
              <EnemyShips :ships="playingItems" :sunk-ships="gs.enemySunkShips" />

              <!-- NEW: Abilities under EnemyShips -->
              <div class="grid gap-2 mt-4">
                <button
                  class="rounded-lg border px-3 py-2 text-left hover:bg-slate-800 border-slate-700 bg-slate-800/60 disabled:opacity-50"
                  :disabled="!canUsePlane"
                  title="Deckt eine komplette Zeile oder Spalte auf. R zum Drehen."
                  @pointerdown.prevent="canUsePlane ? startAbilityFromPanel('plane', $event) : openPopup('Flugzeug verbraucht', 'Das Flugzeug kann nur einmal pro Spiel eingesetzt werden.')"
                >
                  <div class="flex items-start justify-between">
                    <div>
                      <div class="font-medium text-slate-100">Flugzeug</div>
                      <div class="text-[11px] text-slate-400">Komplette Reihe/Spalte</div>
                    </div>
                    <span class="flex items-center gap-2">
      <span v-if="!planeExhausted"
            class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
            title="verbleibend / gesamt">
        {{ planeRemaining }}/{{ PLANE_TOTAL }}
      </span>
      <span v-else
            class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                   bg-emerald-600/15 text-emerald-300 border-emerald-500/40">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
          <path d="M20 6L9 17l-5-5"/>
        </svg>
        Alle verbraucht
      </span>
    </span>
                  </div>
                </button>

                <!-- BOMB -->
                <button
                  class="rounded-lg border px-3 py-2 text-left hover:bg-slate-800 border-slate-700 bg-slate-800/60 disabled:opacity-50"
                  :disabled="!canUseBomb || bombsExhausted"
                  :title="bombsExhausted
                                ? 'Du hast bereits beide Bomben in diesem Spiel verbraucht.'
                                : 'Trifft 5×5 ohne Ecken (21 Felder). 1 Bombe pro Spiel.'"
                                      @pointerdown.prevent="
                        (!bombsExhausted && canUseBomb)
                          ? startAbilityFromPanel('comb', $event)
                          : openPopup(
                              bombsExhausted
                                ? 'Bombe aufgebraucht'
                                : 'Bombe gesperrt',
                              bombsExhausted
                                ? 'Du hast bereits beide Bomben in diesem Spiel verwendet (1/1).'
                                : 'Die Bombe wird erst freigeschaltet, wenn du in diesem Zug 2 Schiffe versenkt hast.'
                            )
                      "
                >
                  <div class="flex items-start justify-between">
                    <div>
                      <div class="font-medium text-slate-100">Bombe</div>
                      <div class="text-[11px] text-slate-400">
                        <template v-if="bombsExhausted">
                          Bereits verwendet (max. 1 pro Spiel)
                        </template>
                        <template v-else>
                          {{ canUseBomb ? 'Sprengt großen Bereich' : 'Erfordert 2 Versenkungen in diesem Zug' }}
                          · 1 Bombe pro Spiel
                        </template>
                      </div>
                    </div>

                    <span class="flex items-center gap-2">
                      <!-- status chip -->
                      <span v-if="bombsExhausted"
                            class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                                   bg-slate-800/70 text-slate-300 border-slate-600/60">
                        Aufgebraucht
                      </span>
                      <span v-else-if="!canUseBomb"
                            class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                                   bg-slate-800/70 text-slate-300 border-slate-600/60">
                        Gesperrt
                      </span>
                      <span v-else
                            class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                                   bg-emerald-600/15 text-emerald-300 border-emerald-500/40">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                          <path d="M20 6L9 17l-5-5"/>
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
                  @pointerdown.prevent="canUseSplatter ? startAbilityFromPanel('splatter', $event) : openPopup('Streuschuss verbraucht', 'Der Streuschuss kann nur zweimal pro Spiel eingesetzt werden.')"
                >
                  <div class="flex items-start justify-between">
                    <div>
                      <div class="font-medium text-slate-100">Streuschuss</div>
                      <div class="text-[11px] text-slate-400">12 zufällige Felder</div>
                    </div>
                    <span class="flex items-center gap-2">
                    <span v-if="!splatterExhausted"
                          class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                                 bg-slate-800/70 text-slate-300 border-slate-600/60"
                          title="verbleibend / gesamt">
                      {{ splatterRemaining }}/{{ SPLATTER_TOTAL }}
                    </span>
                    <span v-else
                          class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                                 bg-emerald-600/15 text-emerald-300 border-emerald-500/40">
                      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M20 6L9 17l-5-5"/>
                      </svg>
                      Alle verbraucht
                    </span>
                  </span>
                  </div>
                </button>
              </div>
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

        <!-- YOU -->
        <section
          :class="[
            'rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl h-full',
            showMyBoard ? 'lg:col-span-2' : 'lg:col-span-1'
          ]"
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-slate-200">Deine Schiffe</h3>
            <button
              :aria-pressed="showMyBoard"
              class="rounded-lg border border-slate-600 px-3 py-1 text-sm text-slate-200 hover:bg-slate-800"
              type="button"
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
              <div
                :class="{ 'pointer-events-none opacity-60': gs.gameOver }"
                class="rounded-lg border border-slate-700/70 bg-slate-900/60 p-4 h-full"
              >
                <PlacementBoard
                  :applyShip="() => {}"
                  :board="gs.myBoard"
                  :canPlace="() => false"
                  :nextSize="null"
                  :orientation="'H'"
                />
              </div>
            </div>
          </div>
        </section>
      </div>

      <div
        v-if="popup.open"
        class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/50"
        @click.self="closePopup()"
      >
        <div class="w-full max-w-sm rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl">
          <div class="mb-2 text-lg font-semibold text-slate-100">{{ popup.title }}</div>
          <div class="mb-4 text-sm text-slate-300">{{ popup.message }}</div>
          <div class="flex justify-end gap-2">
            <button
              class="rounded-md border border-slate-600 px-3 py-1.5 text-slate-200 hover:bg-slate-800"
              @click="closePopup()"
            >
              OK
            </button>
          </div>
        </div>
      </div>

      <GameOverModal :open="gs.gameOver" :winnerName="gs.winnerName" :youWon="gs.youWon" @close="gs.resetForNewGame" />
    </div>
  </div>
</template>

<style scoped>
select:focus, input:focus, button:focus { outline: none; }

.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
