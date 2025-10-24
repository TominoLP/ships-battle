<script lang="ts" setup>
import { computed, ref, unref } from 'vue';
import type { Dir, PlacedShip } from '@/src/types';
import BaseBoard from '@/src/components/BaseBoard.vue';

const props = defineProps<{
  board: number[][]
  canPlace: (x: number, y: number, size: number, dir: Dir, noTouch?: boolean) => boolean
  applyShip: (x: number, y: number, size: number, dir: Dir) => void
  nextSize?: number | null
  orientation: Dir
  placedShips: PlacedShip[]
}>();

const emit = defineEmits<{
  (e: 'shipDragStart', payload: { ship: PlacedShip; event: PointerEvent }): void
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

function handleCellHover(x: number, y: number) {
  hoverX.value = x;
  hoverY.value = y;
}

function handleCellClick(x: number, y: number, cell: number) {
  if (cell !== 0) return;
  if (nextSize.value == null) return;
  if (!props.canPlace(x, y, nextSize.value, orientation.value)) return;
  props.applyShip(x, y, nextSize.value, orientation.value);
  clearHover();
}

function cellBelongsToShip(ship: PlacedShip, x: number, y: number) {
  for (let i = 0; i < ship.size; i++) {
    const cx = ship.dir === 'H' ? ship.x + i : ship.x;
    const cy = ship.dir === 'V' ? ship.y + i : ship.y;
    if (cx === x && cy === y) return true;
  }
  return false;
}

function handlePointerDown(ev: PointerEvent, x: number, y: number, cell: number) {
  if (cell !== 1) return;
  if (ev.button !== 0) return;
  const ship = props.placedShips.find(s => cellBelongsToShip(s, x, y));
  if (!ship) return;
  ev.preventDefault();
  ev.stopPropagation();
  emit('shipDragStart', { ship, event: ev });
}

function getCellFromPoint(clientX: number, clientY: number) {
  const base = baseRef.value as any;
  if (!base?.getCellFromPoint) return null;
  return base.getCellFromPoint(clientX, clientY) ?? null;
}

defineExpose({ getCellFromPoint, setExternalHover, clearHover, getHoverCell });
</script>

<template>
  <BaseBoard
    ref="baseRef"
    :board="board"
    :getCellClass="(cell:number,x:number,y:number) => {
      if (cell === 4) return 'bg-red-500'
      if (cell === 3) return 'bg-slate-700'
      if (cell === 1) return 'bg-emerald-500/80 border-emerald-500/50 shadow-[inset_0_0_0_1px_rgba(16,185,129,.35),0_6px_16px_-6px_rgba(16,185,129,.35)]'
      if (cell === 0 && inPreview(x,y)) {
        return previewIsValid()
          ? 'bg-blue-400/15 ring-2 ring-blue-400/70'
          : 'bg-red-500/10 ring-2 ring-red-500/70'
      }
      return cell === 0 ? 'bg-slate-800/60 hover:bg-slate-800/80' : ''
    }"
    :onCellHover="handleCellHover"
    :onCellClick="handleCellClick"
    :onCellPointerDown="handlePointerDown"
    @mouseleave="clearHover"
    showHint="Ziehe das nächste Schiff auf das Board. Blau = gültig, Rot = ungültig. Drücke R zum Drehen."
  />
</template>
