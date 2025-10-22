<script lang="ts" setup>

import BaseBoard from '@/src/components/BaseBoard.vue';

const props = defineProps<{ enemyBoard: number[][]; disabled?: boolean }>();
const emit = defineEmits<{ (e: 'fire', x: number, y: number): void }>();

function getCellClass(cell: number, x: number, y: number) {
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
</script>

<template>
  <BaseBoard
    :board="enemyBoard"
    :getCellClass="getCellClass"
    :onCellClick="handleClick"
    showHint="Klicke auf ein Feld, um zu schießen."
  />
</template>
