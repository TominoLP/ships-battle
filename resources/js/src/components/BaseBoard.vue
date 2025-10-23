<!-- BaseBoard.vue -->
<script lang="ts" setup>
import { ref, computed } from 'vue';

const props = defineProps<{
  board: number[][]
  letters?: string[]
  showHint?: string
  getCellClass: (cell: number, x: number, y: number) => string | string[]
  onCellClick?: (x: number, y: number, cell: number) => void
  /** NEW: let parents receive hover events */
  onCellHover?: (x: number, y: number, cell: number) => void
}>();

const emit = defineEmits<{ (e: 'cellClick', x: number, y: number): void }>();
const letters = props.letters ?? 'ABCDEFGHIJKL'.split('');

const gridEl = ref<HTMLElement | null>(null);

// quick references
const rows = computed(() => props.board.length);
const cols = computed(() => (props.board[0]?.length ?? 0));

/** NEW: robust hit-test using actual DOM sizes & CSS gap */
function getCellFromPoint(clientX: number, clientY: number) {
  const grid = gridEl.value;
  if (!grid) return null;

  const rect = grid.getBoundingClientRect();
  const xRel = clientX - rect.left;
  const yRel = clientY - rect.top;
  if (xRel < 0 || yRel < 0 || xRel >= rect.width || yRel >= rect.height) return null;

  const style = getComputedStyle(grid);
  const gapX = parseFloat(style.columnGap || '0') || 0;
  const gapY = parseFloat(style.rowGap || '0') || 0;

  // measure one cell (has data attribute)
  const sample = grid.querySelector('[data-cell="1"]') as HTMLElement | null;
  let cw: number, ch: number;
  if (sample) {
    const cr = sample.getBoundingClientRect();
    cw = cr.width; ch = cr.height;
  } else {
    // fallback: derive from grid size
    cw = (rect.width  - gapX * (cols.value - 1)) / cols.value;
    ch = (rect.height - gapY * (rows.value - 1)) / rows.value;
  }

  const stepX = cw + gapX;
  const stepY = ch + gapY;

  const cx = Math.floor(xRel / stepX);
  const cy = Math.floor(yRel / stepY);
  if (cx < 0 || cy < 0 || cx >= cols.value || cy >= rows.value) return null;

  return { x: cx, y: cy };
}

// expose API for EnemyBoard/App.vue
defineExpose({
  getGridEl: () => gridEl.value,
  getCellFromPoint,
});
</script>

<template>
  <div class="space-y-2">
    <div class="relative mt-0 w-fit select-none rounded-2xl p-3 backdrop-blur mr-6">
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
                data-cell="1"
              :class="getCellClass(cell, x, y)"
              class="relative h-8 w-8 sm:h-9 sm:w-9 rounded-lg transition-all duration-150 border-none"
              @click="onCellClick && onCellClick(x, y, cell); emit('cellClick', x, y)"
              @mouseenter="onCellHover && onCellHover(x, y, cell)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-if="showHint" class="text-center text-xs text-slate-400">{{ showHint }}</p>
  </div>
</template>
