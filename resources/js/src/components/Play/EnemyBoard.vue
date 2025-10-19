<script setup lang="ts">
defineProps<{ enemyBoard: number[][]; disabled?: boolean }>()
defineEmits(['fire'])
</script>

<template>
  <div class="grid grid-cols-12 justify-center mx-auto w-fit select-none p-3 rounded-xl bg-slate-950/40 border border-slate-700 gap-[3px]">
    <div v-for="(row, y) in enemyBoard" :key="y" class="contents">
      <div
          v-for="(cell, x) in row"
          :key="x"
          class="w-8 h-8 sm:w-9 sm:h-9 border border-slate-700 rounded-[6px] cursor-pointer transition"
          :class="{
          'bg-slate-700': cell === 1,     // miss
          'bg-red-600/80': cell === 2,    // hit
          'bg-slate-800/50 hover:bg-red-950/30': cell === 0
        }"
          @click="!disabled && $emit('fire', x, y)"
      />
    </div>
  </div>
</template>