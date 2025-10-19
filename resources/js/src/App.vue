<script setup lang="ts">
import { watchEffect, proxyRefs, computed, ref } from 'vue'
import CreatePanel from '@/src/components/Join/CreatePanel.vue'
import LobbyPanel from '@/src/components/LobbyPanel.vue'
import ShipPalette from '@/src/components/Placement/ShipPalette.vue'
import PlacementBoard from '@/src/components/Placement/PlacementBoard.vue'
import EnemyBoard from '@/src/components/Play/EnemyBoard.vue'
import MessageLog from '@/src/components/Log/MessageLog.vue'
import GameOverModal from '@/src/components/Modals/GameOverModal.vue'

import { useGameState } from '@/src/composables/useGameState'
import { usePlacement } from '@/src/composables/placement'
import type { ShipSpec } from '@/src/types'

const gs = proxyRefs(useGameState())

/** original game fleet (counts) */
const fleet: ShipSpec[] = [
  { name: 'Battleship', size: 5, count: 1 },
  { name: 'Cruiser',    size: 4, count: 2 },
  { name: 'Destroyer',  size: 3, count: 3 },
  { name: 'Submarine',  size: 2, count: 4 },
]

/** optional: German display names by size (matches your screenshots) */
const nameBySize: Record<number, string> = {
  5: 'Flugzeugträger',
  4: 'Schlachtschiff',
  3: 'Kreuzer',
  2: 'U-Boot',
}

/** UI-friendly fleet for the palette */
const uiFleet = computed(() =>
    fleet.map(f => ({
      name: nameBySize[f.size] ?? f.name,
      size: f.size,
      total: f.count,
    }))
)

const placement = usePlacement(12, fleet)

// keep your board in sync with placement
watchEffect(() => { gs.myBoard = placement.board.value })

/** Selected ship size from the by-type palette (user choice) */
const selectedSize = ref<number | undefined>(undefined)

/** Plain placed sizes array for the palette (handles both ref and plain array) */
const placedSizes = computed<number[]>(() => {
  const arr: any = (placement as any).placedShips
  const list = Array.isArray(arr) ? arr : arr?.value ?? []
  return list.map((s: any) => s.size)
})

/** Orientation as a plain union ('H' | 'V') for the palette */
const orientationStr = computed<'H' | 'V'>(() => placement.orientation.value as 'H' | 'V')

/** When user picks a ship type in the palette */
function pickSize(size: number) {
  selectedSize.value = size
}

/** Drag starts with the selected type + current orientation */
function beginDrag(e: DragEvent) {
  if (!selectedSize.value) return
  e.dataTransfer?.setData('text/plain', String(selectedSize.value))
  e.dataTransfer?.setData('dir', orientationStr.value)
}

/** Wrap applyShip to optionally clear the selection if that size is exhausted */
function applyShipAndMaybeClear(x: number, y: number, size: number, dir: 'H' | 'V') {
  placement.applyShip(x, y, size, dir)
  // if all ships of this size are placed, clear selection
  const totalOfSize = uiFleet.value.find(f => f.size === size)?.total ?? 0
  const nowPlaced = placedSizes.value.filter(s => s === size).length
  if (nowPlaced >= totalOfSize) selectedSize.value = undefined
}

const statusMessage = computed(() => {
  switch (gs.step) {
    case 'join': return 'Gib deinen Namen ein und erstelle oder trete einem Spiel bei.'
    case 'lobby': return 'Warte in der Lobby, bis beide bereit sind.'
    case 'placing': return 'Platziere deine Schiffe auf dem Spielfeld.'
    case 'playing': return gs.gameOver ? 'Spiel beendet.' : (gs.myTurn ? 'Dein Zug!' : 'Gegner am Zug…')
    default: return ''
  }
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-6 sm:p-8 text-slate-200">
    <div class="max-w-[1800px] mx-auto">
      <!-- Header -->
      <div class="mb-8 text-center">
        <div class="mb-2 flex items-center justify-center gap-3">
          <svg class="h-10 w-10 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="5" r="2.5"/>
            <path d="M12 8.5v12"/>
            <path d="M7 11h10"/>
            <path d="M12 20.5c-3.5 0-6-2.5-6-5.5"/>
            <path d="M12 20.5c3.5 0 6-2.5 6-5.5"/>
          </svg>
          <h1 class="text-3xl font-semibold text-blue-400">Schiffeversenken</h1>
        </div>
        <p class="text-slate-400">{{ statusMessage }}</p>
        <p v-if="gs.gameCode" class="mt-1 text-slate-500">
          Code: <span class="font-mono text-blue-300">{{ gs.gameCode }}</span>
        </p>
      </div>

      <div class="flex flex-wrap items-start justify-center gap-6">
        <!-- Left column: palette / actions -->
        <div class="flex w-full flex-col gap-4" :class="gs.step === 'join' ? 'max-w-md' : 'sm:w-80'">
          <div class="rounded-xl border border-slate-700 bg-slate-900/80 p-6 shadow-xl backdrop-blur">
            <div class="mb-6 flex items-center justify-center gap-2">
              <h3 class="text-lg font-medium text-slate-200">{{ gs.step === 'join' ? 'Willkommen' : 'Schiffe' }}</h3>
            </div>

            <!-- placing -->
            <div v-if="gs.step === 'placing'" class="space-y-4">
              <ShipPalette
                  :fleet="uiFleet"
                  :placedSizes="placedSizes"
                  :selectedSize="selectedSize ?? null"
                  :orientation="orientationStr"
                  @pickSize="pickSize"
                  @toggleOrientation="placement.orientation.value = placement.orientation.value === 'H' ? 'V' : 'H'"
                  @undo="placement.removeLast"
                  @reset="() => { placement.reset(); selectedSize = undefined }"
              />

              <div class="fmt-2 w-full rounded-lg text-slate-400 mt-6">
                <button
                    draggable="true"
                    @dragstart="beginDrag"
                    class="rounded-lg w-full border border-slate-600 bg-slate-800 px-3 py-2 transition-colors hover:bg-slate-700"
                >
                  <div class="flex items-center gap-1.5 mt-2 mb-2">
                    <div
                        v-for="i in selectedSize"
                        :key="i"
                        class="h-4 flex-1 rounded-md border transition border-blue-400/60 bg-blue-500/50"
                    />
                  </div>
                  Ziehe das nächstes Schiff
                </button>
              </div>

              <button
                  class="mt-2 w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-500 disabled:opacity-50"
                  :disabled="!placement.allPlaced"
                  @click="gs.readyUp(placement.placedShips)"
              >
                Spiel starten
              </button>
            </div>

            <!-- join -->
            <div v-else-if="gs.step === 'join'">
              <CreatePanel
                  v-model:name="gs.name"
                  v-model:gameCode="gs.gameCode"
                  @create="gs.createGame"
                  @join="gs.joinGame"
              />
            </div>

            <!-- lobby -->
            <div v-else-if="gs.step === 'lobby'" class="space-y-4">
              <LobbyPanel :gameCode="gs.gameCode" :isReady="gs.isReady" />
            </div>

            <!-- default actions -->
            <div v-else class="space-y-2">
              <button
                  @click="gs.resetForNewGame"
                  class="w-full rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 text-slate-200 transition-colors hover:bg-slate-700"
              >
                Neues Spiel
              </button>
            </div>
          </div>
        </div>

        <!-- Center column: boards -->
        <div v-if="gs.step !== 'join'" class="flex flex-col gap-6">
          <!-- enemy board -->
          <div v-if="gs.step === 'playing' || gs.step === 'placing'" class="rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl">
            <h3 class="mb-3 text-center text-slate-200">⚔️ Gegnerisches Spielfeld</h3>
            <EnemyBoard
                v-if="gs.step === 'playing'"
                :enemyBoard="gs.enemyBoard"
                :disabled="gs.gameOver || !gs.myTurn"
                @fire="(x, y) => gs.fire(x, y)"
            />
            <p v-else class="text-center text-sm text-slate-500">Sobald das Spiel startet, erscheint hier das Gegner-Board.</p>
          </div>

          <!-- player board -->
          <div v-if="gs.step === 'placing' || gs.step === 'playing'" class="rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl pr-8">
            <h3 class=" text-center text-slate-200">🛡️ Dein Spielfeld</h3>

            <!-- placing: interactive -->
            <PlacementBoard
                v-if="gs.step === 'placing'"
                :board="placement.board"
                :canPlace="placement.canPlace"
                :applyShip="applyShipAndMaybeClear"
                :nextSize="selectedSize"
                :orientation="placement.orientation"
            />

            <!-- playing: read-only view of my board -->
            <div v-else class="opacity-100" :class="{ 'pointer-events-none opacity-60': gs.gameOver }">
              <PlacementBoard
                  :board="gs.myBoard"
                  :canPlace="() => false"
                  :applyShip="() => {}"
                  :nextSize="undefined"
                  :orientation="placement.orientation"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Modals -->
      <GameOverModal
          :open="gs.gameOver"
          :youWon="gs.youWon"
          :winnerName="gs.winnerName"
          @close="gs.resetForNewGame"
      />
    </div>
  </div>
</template>

<style scoped>
select:focus, input:focus, button:focus { outline: none }
</style>
