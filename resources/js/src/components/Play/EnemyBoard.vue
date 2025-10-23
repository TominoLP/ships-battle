<script lang="ts" setup>
import BaseBoard from '@/src/components/BaseBoard.vue';
import { ref } from 'vue';

type Cell = { x: number; y: number };

const props = defineProps<{
  enemyBoard: number[][];
  disabled?: boolean;
  previewCells?: Cell[]; // optional overlay preview
}>();

const emit = defineEmits<{
  (e: 'fire', x: number, y: number): void;
  (e: 'hover', x: number, y: number, cell: number): void;
}>();

function isPreview(x: number, y: number) {
  const list = props.previewCells ?? [];
  for (let i = 0; i < list.length; i++) {
    if (list[i].x === x && list[i].y === y) return true;
  }
  return false;
}

function getCellClass(cell: number, x: number, y: number) {
  if (isPreview(x, y)) {
    return 'bg-blue-400/15 ring-2 ring-blue-400/70';
  }
  if (cell === 1) return 'bg-slate-700';
  if (cell === 2) return 'bg-red-600/80';
  if (cell === 0)
    return props.disabled
      ? 'bg-slate-800/50'
      : 'bg-slate-800/50 hover:bg-red-950/30 cursor-pointer';
  return 'cursor-default';
}

function handleClick(x: number, y: number, cell: number) {
  if (props.disabled || cell !== 0) return;
  emit('fire', x, y);
}

function handleHover(x: number, y: number, cell: number) {
  emit('hover', x, y, cell);
}

const baseRef = ref<InstanceType<typeof BaseBoard> | null>(null);

function getGridEl() {
  return baseRef.value?.getGridEl?.() ?? null;
}
function getCellFromPoint(x: number, y: number) {
  return baseRef.value?.getCellFromPoint?.(x, y) ?? null;
}

defineExpose({ getGridEl, getCellFromPoint });


</script>

<template>
  <BaseBoard
    :board="enemyBoard"
    :getCellClass="getCellClass"
    :onCellClick="handleClick"
    :onCellHover="handleHover"
    :showHint="(previewCells?.length ?? 0) > 0
      ? 'Geist aktiv – klicke zum Anwenden.'
      : 'Klicke auf ein Feld, um zu schießen.'"
  />
</template>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: .45; }
  50% { opacity: .9; }
}
</style>
