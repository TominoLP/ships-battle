<script lang="ts" setup>
import { computed, ref, unref } from 'vue';
import type { Dir } from '@/src/types';
import BaseBoard from '@/src/components/BaseBoard.vue';

const props = defineProps<{
  board: number[][]
  canPlace: (x: number, y: number, size: number, dir: Dir, noTouch?: boolean) => boolean
  applyShip: (x: number, y: number, size: number, dir: Dir) => void
  nextSize?: number | null
  orientation: Dir
}>();

const board = computed(() => unref(props.board));
const nextSize = computed(() => unref(props.nextSize) as number | null | undefined);
const orientation = computed<Dir>(() => unref(props.orientation) as Dir);

const hoverX = ref<number | null>(null);
const hoverY = ref<number | null>(null);

function clearHover() {
  hoverX.value = null;
  hoverY.value = null;
}

function setExternalHover(x: number | null, y: number | null) {
  hoverX.value = x;
  hoverY.value = y;
}

function getHoverCell() {
  return (hoverX.value == null || hoverY.value == null) ? null : { x: hoverX.value, y: hoverY.value };
}

function inPreview(x: number, y: number) {
  if (nextSize.value == null) return false;
  if (hoverX.value === null || hoverY.value === null) return false;
  const s = nextSize.value;
  const hx = hoverX.value, hy = hoverY.value;
  return orientation.value === 'H'
    ? (y === hy && x >= hx && x < hx + s)
    : (x === hx && y >= hy && y < hy + s);
}

function previewIsValid() {
  if (nextSize.value == null || hoverX.value === null || hoverY.value === null) return false;
  return props.canPlace(hoverX.value, hoverY.value, nextSize.value as number, orientation.value);
}

const baseRef = ref<InstanceType<typeof BaseBoard> | null>(null);

const isSm = () => typeof window !== 'undefined' && window.matchMedia?.('(min-width: 640px)').matches;
const cellPx = computed(() => (isSm() ? 36 : 32));
const gapPx = 6;

function getCellFromPoint(clientX: number, clientY: number) {
  const grid = (baseRef.value as any)?.getGridEl?.() as HTMLElement | null;
  if (!grid) return null;
  const r = grid.getBoundingClientRect();
  const localX = clientX - r.left;
  const localY = clientY - r.top;
  if (localX < 0 || localY < 0 || localX > r.width || localY > r.height) return null;
  const step = cellPx.value + gapPx;
  let x = Math.floor(localX / step);
  let y = Math.floor(localY / step);
  x = Math.max(0, Math.min(11, x));
  y = Math.max(0, Math.min(11, y));
  return { x, y };
}

defineExpose({ getCellFromPoint, setExternalHover, clearHover, getHoverCell });
</script>

<template>
  <BaseBoard
    ref="baseRef"
    :board="board"
    :getCellClass="(cell:number,x:number,y:number) => {
      if (cell === 4) return 'bg-red-600/80'
      if (cell === 3) return 'bg-slate-700'
      if (cell === 1) return 'bg-emerald-500/80 border-emerald-400/50 shadow-[inset_0_0_0_1px_rgba(16,185,129,.35),0_6px_16px_-6px_rgba(16,185,129,.35)]'
      if (cell === 0 && inPreview(x,y)) {
        return previewIsValid()
          ? 'bg-blue-400/15 ring-2 ring-blue-400/70'
          : 'bg-red-500/10 ring-2 ring-red-500/70'
      }
      // base empty
      return cell === 0 ? 'bg-slate-800/60 hover:bg-slate-800/80' : ''
    }"
    showHint="Ziehe das nächste Schiff auf das Board. Blau = gültig, Rot = ungültig. Drücke R zum Drehen."
  />
</template>
