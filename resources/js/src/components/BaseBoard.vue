<!-- BaseBoard.vue -->
<script lang="ts" setup>
import { ref } from 'vue';

const props = defineProps<{
  board: number[][]
  letters?: string[]
  showHint?: string
  getCellClass: (cell: number, x: number, y: number) => string
  onCellClick?: (x: number, y: number, cell: number) => void
}>();

const emit = defineEmits<{ (e: 'cellClick', x: number, y: number): void }>();
const letters = props.letters ?? 'ABCDEFGHIJKL'.split('');

const gridEl = ref<HTMLElement | null>(null);
defineExpose({ getGridEl: () => gridEl.value });
</script>

<template>
  <div class="space-y-2">
    <div class="relative mt-0 w-fit select-none rounded-2xl p-3 backdrop-blur">
      <div class="grid items-start gap-[6px]" style="grid-template-columns: auto 1fr;">
        <!-- Left letters -->
        <div class="grid gap-[6px] mt-9" style="grid-template-rows: auto repeat(12,minmax(2rem,auto));">
          <div />
          <div v-for="(ch, i) in letters" :key="'L'+i"
               class="h-8 sm:h-9 w-8 sm:w-9 flex items-center justify-center text-xs text-slate-400">
            {{ ch }}
          </div>
        </div>

        <!-- Top numbers + grid -->
        <div class="grid gap-[6px]">
          <div class="grid grid-cols-12 gap-[6px]">
            <div v-for="n in 12" :key="'N'+n"
                 class="h-8 sm:h-9 w-8 sm:w-9 flex items-center justify-center text-xs text-slate-400">
              {{ n }}
            </div>
          </div>

          <div ref="gridEl" class="grid grid-cols-12 gap-[6px]" data-board-grid>
            <div v-for="(row, y) in board" :key="y" class="contents">
              <div
                v-for="(cell, x) in row"
                :key="x"
                :class="getCellClass(cell, x, y)"
                class="relative h-8 w-8 sm:h-9 sm:w-9 rounded-lg transition-all duration-150 border-none"
                @click="onCellClick && onCellClick(x, y, cell); emit('cellClick', x, y)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-if="showHint" class="text-center text-xs text-slate-400">{{ showHint }}</p>
  </div>
</template>
