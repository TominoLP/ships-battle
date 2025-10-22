<script lang="ts" setup>
import { computed } from 'vue';
import type { PlacedShip } from '@/src/types';

type Item = { key: string; size: number; hits: number; sunk: boolean }

const props = defineProps<{
  ships: any
  board: number[][]
}>();

const isPlacement = computed(() =>
  props.ships && typeof props.ships === 'object' && 'placedShips' in props.ships
);

// ------- Mode 1: we have placedShips with positions -------
const itemsFromPlaced = computed<Item[]>(() => {
  if (!isPlacement.value) return [];
  const placed: PlacedShip[] = Array.isArray(props.ships.placedShips)
    ? props.ships.placedShips
    : (props.ships.placedShips?.value ?? []);
  const b = props.board;

  // make stable per-size keys if none exist
  const perSizeIdx: Record<number, number> = {};

  return placed.map((s) => {
    perSizeIdx[s.size] = (perSizeIdx[s.size] ?? 0) + 1;
    const key = `${s.size}-${perSizeIdx[s.size]}`;
    let hits = 0;
    for (let i = 0; i < s.size; i++) {
      const x = s.dir === 'H' ? s.x + i : s.x;
      const y = s.dir === 'V' ? s.y + i : s.y;
      if (b[y]?.[x] === 4) hits++;
    }
    return { key, size: s.size, hits, sunk: hits === s.size };
  });
});

// ------- Mode 2: we only have {key,size}[]; rebuild ships from board -------
function extractShipsFromBoard(board: number[][]): { size: number; hits: number; x: number; y: number }[] {
  const H = board.length;
  const W = board[0]?.length ?? 0;
  const seen: boolean[][] = Array.from({ length: H }, () => Array(W).fill(false));
  const isShip = (y: number, x: number) => board[y]?.[x] === 1 || board[y]?.[x] === 4;
  const ships: { size: number; hits: number; x: number; y: number }[] = [];

  for (let y = 0; y < H; y++) {
    for (let x = 0; x < W; x++) {
      if (!isShip(y, x) || seen[y][x]) continue;

      // determine direction
      const horiz = isShip(y, x + 1);
      const vert = isShip(y + 1, x);

      let size = 0;
      let hits = 0;

      if (horiz) {
        // walk right
        let cx = x;
        while (isShip(y, cx)) {
          seen[y][cx] = true;
          if (board[y][cx] === 4) hits++;
          size++;
          cx++;
        }
      } else if (vert) {
        // walk down
        let cy = y;
        while (isShip(cy, x)) {
          seen[cy][x] = true;
          if (board[cy][x] === 4) hits++;
          size++;
          cy++;
        }
      } else {
        // single cell ship (size 1) — not in your fleet, but keep generic
        size = 1;
        hits = board[y][x] === 4 ? 1 : 0;
        seen[y][x] = true;
      }

      ships.push({ size, hits, x, y });
    }
  }

  // stable order: by size asc, then by y,x
  ships.sort((a, b) => a.size - b.size || a.y - b.y || a.x - b.x);
  return ships;
}

const itemsFromKeys = computed<Item[]>(() => {
  if (isPlacement.value) return [];
  const instanceList: Array<{ key: string; size: number }> = Array.isArray(props.ships) ? props.ships : [];
  const detected = extractShipsFromBoard(props.board);

  // group detected ships by size for pairing
  const bySize: Record<number, { size: number; hits: number }[]> = {};
  for (const d of detected) {
    (bySize[d.size] ||= []).push({ size: d.size, hits: d.hits });
  }

  // ensure deterministic pairing
  for (const s in bySize) bySize[s].sort((a, b) => a.hits - b.hits);

  return instanceList.map(({ key, size }) => {
    const pool = bySize[size] && bySize[size].length ? bySize[size] : null;
    const hits = pool ? (pool.shift()!.hits) : 0;
    return { key, size, hits, sunk: hits >= size };
  });
});

// Final list (prefer placedShips when available)
const items = computed<Item[]>(() =>
  isPlacement.value ? itemsFromPlaced.value : itemsFromKeys.value
);
</script>

<template>
  <div class="grid grid-cols-1 gap-2">
    <div
      v-for="ship in items"
      :key="ship.key"
      :aria-label="`Ship ${ship.key}: ${ship.hits}/${ship.size} hits`"
      class="rounded-lg border border-slate-700/70 bg-slate-900/60 p-2"
    >
      <div class="flex items-center gap-1.5">
        <div
          v-for="i in ship.size"
          :key="i"
          :class="i <= ship.hits
            ? 'border-rose-500/50 bg-rose-500/80 shadow-[inset_0_0_0_1px_rgba(244,63,94,.35),0_6px_14px_-6px_rgba(244,63,94,.5)]'
            : 'border-emerald-500/50 bg-emerald-500/80 shadow-[inset_0_0_0_1px_rgba(16,185,129,.3),0_6px_14px_-6px_rgba(16,185,129,.45)]'"
          class="h-4 flex-1 rounded-md border transition"
        />
      </div>
    </div>
  </div>
</template>
