<script setup lang="ts">
import { computed, ref, unref } from 'vue'
import type { Dir } from '@/src/types'

const props = defineProps<{
  board: number[][] | any
  canPlace: (x: number, y: number, size: number, dir: Dir, noTouch?: boolean) => boolean
  applyShip: (x: number, y: number, size: number, dir: Dir) => void
  nextSize?: number | any
  orientation: Dir | any
}>()

const board = computed<number[][]>(() => unref(props.board))
const nextSize = computed<number | undefined>(() => unref(props.nextSize) as any)
const orientation = computed<Dir>(() => unref(props.orientation) as Dir)

const letters = 'ABCDEFGHIJKL'.split('');

/** hover anchor for preview while dragging */
const hoverX = ref<number | null>(null)
const hoverY = ref<number | null>(null)

function clearHover() {
  hoverX.value = null
  hoverY.value = null
}

function handleDragOver(e: DragEvent, x: number, y: number) {
  e.preventDefault()
  hoverX.value = x
  hoverY.value = y
}

function handleDrop(e: DragEvent, x: number, y: number) {
  e.preventDefault()
  if (nextSize.value === undefined) return
  const dir = (e.dataTransfer?.getData('dir') as Dir) || orientation.value
  if (props.canPlace(x, y, nextSize.value, dir)) props.applyShip(x, y, nextSize.value, dir)
  clearHover()
}

/** compute if a cell is part of the current preview */
function inPreview(x: number, y: number) {
  if (nextSize.value === undefined) return false
  if (hoverX.value === null || hoverY.value === null) return false
  const hx = hoverX.value, hy = hoverY.value
  if (orientation.value === 'H') {
    return y === hy && x >= hx && x < hx + (nextSize.value ?? 0)
  } else {
    return x === hx && y >= hy && y < hy + (nextSize.value ?? 0)
  }
}

function previewIsValid() {
  if (nextSize.value === undefined) return false
  if (hoverX.value === null || hoverY.value === null) return false
  return props.canPlace(hoverX.value, hoverY.value, nextSize.value, orientation.value)
}
</script>

<template>
  <div class="space-y-2">
    <!-- board frame -->
    <div class="relative mt-0 mx-auto w-fit select-none rounded-2xl p-3 backdrop-blur">
      <!-- labels + board -->
      <div class="grid items-start gap-[6px]" style="grid-template-columns: auto 1fr;">
        <!-- left letters -->
        <div class="grid gap-[6px] mt-9" style="grid-template-rows: auto repeat(12, minmax(2rem,auto));">
          <div /> <!-- empty corner -->
          <div
              v-for="(ch, i) in letters"
              :key="'L'+i"
              class=" h-8 sm:h-9 w-8 sm:w-9 flex items-center justify-center text-xs text-slate-400"
          >
            {{ ch }}
          </div>
        </div>

        <!-- top numbers + board -->
        <div class="grid gap-[6px]">
          <!-- top numbers -->
          <div class="grid grid-cols-12 gap-[6px]">
            <div
                v-for="n in 12"
                :key="'N'+n"
                class="h-8 sm:h-9 w-8 sm:w-9 flex items-center justify-center text-xs text-slate-400"
            >
              {{ n }}
            </div>
          </div>

          <!-- your existing 12 x 12 grid (unchanged) -->
          <div class="grid grid-cols-12 gap-[6px]">
            <div v-for="(row, y) in board" :key="y" class="contents">
              <div
                  v-for="(cell, x) in row"
                  :key="x"
                  @dragover="e => handleDragOver(e, x, y)"
                  @dragleave="clearHover"
                  @drop="e => handleDrop(e, x, y)"
                  class="relative h-8 w-8 sm:h-9 sm:w-9 rounded-lg transition-all duration-150 border-none"
                  :class="[
                  !cell && !inPreview(x, y) ? 'bg-slate-800/60 hover:bg-slate-800/80' : '',
                  cell
                    ? 'bg-emerald-500/80 border-emerald-400/50 shadow-[inset_0_0_0_1px_rgba(16,185,129,.35),0_6px_16px_-6px_rgba(16,185,129,.35)]'
                    : '',
                  !cell && inPreview(x, y)
                    ? (previewIsValid()
                      ? 'ring-2 ring-blue-400/70 bg-blue-400/15'
                      : 'ring-2 ring-red-500/70 bg-red-500/10')
                    : ''
                ]"
              >
                <span class="pointer-events-none absolute inset-0 rounded-[10px]"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p class="text-center text-xs text-slate-400">
      Ziehe das nächste Schiff auf das Board. Blau = gültig, Rot = ungültig.
    </p>
  </div>
</template>
