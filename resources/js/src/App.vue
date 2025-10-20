<script setup lang="ts">
import { watchEffect, proxyRefs, computed, ref, onMounted, onBeforeUnmount } from 'vue'
import CreatePanel from '@/src/components/Join/CreatePanel.vue'
import LobbyPanel from '@/src/components/LobbyPanel.vue'
import PlacementBoard from '@/src/components/Placement/PlacementBoard.vue'
import EnemyBoard from '@/src/components/Play/EnemyBoard.vue'
import GameOverModal from '@/src/components/Modals/GameOverModal.vue'
import ShipPalette from '@/src/components/Placement/ShipPalette.vue'
import ActiveShips from '@/src/components/Placement/ActiveShips.vue'
import Statistics from '@/src/components/Stats/Statistics.vue'

import { useGameState } from '@/src/composables/useGameState'
import { usePlacement } from '@/src/composables/placement'
import type { ShipSpec } from '@/src/types'
import EnemyShips from '@/src/components/Placement/EnemyShips.vue';

const gs = proxyRefs(useGameState())

// Fleet spec
const fleet: ShipSpec[] = [
  { name: 'Battleship', size: 5, count: 1 },
  { name: 'Cruiser',    size: 4, count: 2 },
  { name: 'Destroyer',  size: 3, count: 3 },
  { name: 'Submarine',  size: 2, count: 4 },
]

const de: Record<number, string> = { 5:'Flugzeugträger', 4:'Schlachtschiff', 3:'Kreuzer', 2:'U-Boot' }

const showMyBoard = ref(false)

// PWA state
const placement = usePlacement(12, fleet)
watchEffect(() => { gs.myBoard = placement.board.value })

// UI-friendly fleet for ShipPalette (type-based)
const uiFleet = computed(() =>
  fleet.map(f => ({ name: de[f.size] ?? f.name, size: f.size, total: f.count }))
)

// placed sizes (for palette + compact list)
const placedSizes = computed<number[]>(() => {
  const arr: any = (placement as any).placedShips
  const list = Array.isArray(arr) ? arr : arr?.value ?? []
  return list.map((s: any) => s.size)
})

const orientationStr = computed<'H'|'V'>(() => placement.orientation.value as 'H'|'V')

// SELECTION (type-based for placing)
const selectedSize = ref<number | null>(null)
function pickSize(size: number | null) { selectedSize.value = size }

// ------- Drag ghost for placing -------
const boardRef = ref<any>(null)
const isDragging = ref(false)
const dragGhostEl = ref<HTMLElement | null>(null)

function buildGhost(size:number, dir:'H'|'V') {
  const cell = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32
  const gap = 6
  const cols = dir==='H'? size:1, rows = dir==='V'? size:1
  const host = document.createElement('div')
  host.className = 'drag-ghost pointer-events-none'
  Object.assign(host.style,{position:'fixed',width:`${cols*cell+(cols-1)*gap}px`,height:`${rows*cell+(rows-1)*gap}px`})
  const grid = document.createElement('div')
  Object.assign(grid.style,{display:'grid',gridTemplateColumns:`repeat(${cols},${cell}px)`,gridTemplateRows:`repeat(${rows},${cell}px)`,gap:`${gap}px`})
  for (let i=0;i<size;i++){ const sq=document.createElement('div'); sq.className='rounded-[10px] bg-blue-400/25'; sq.style.width=`${cell}px`; sq.style.height=`${cell}px`; grid.appendChild(sq) }
  host.appendChild(grid); document.body.appendChild(host); return host
}
function destroyGhost(){ if(dragGhostEl.value?.parentNode) dragGhostEl.value.parentNode.removeChild(dragGhostEl.value); dragGhostEl.value=null }
function moveGhost(ev:MouseEvent){ if(!dragGhostEl.value) return; const c=window.matchMedia?.('(min-width: 640px)').matches?36:32; dragGhostEl.value.style.left=`${ev.clientX-c/2}px`; dragGhostEl.value.style.top=`${ev.clientY-c/2}px` }

function startPointerDrag(ev:MouseEvent){
  if(!selectedSize.value) return
  destroyGhost()
  dragGhostEl.value = buildGhost(selectedSize.value, orientationStr.value)
  Object.assign(dragGhostEl.value.style,{left:'-9999px',top:'-9999px',zIndex:'9999'})
  isDragging.value = true; document.body.style.userSelect='none'; moveGhost(ev)
  window.addEventListener('mousemove', onMove, {passive:false})
  window.addEventListener('mouseup', onUp, {passive:false, once:true})
  window.addEventListener('keydown', onKeyDuringDrag, {capture:true})
}
function onMove(ev:MouseEvent){
  if(!isDragging.value) return
  moveGhost(ev)
  const pt = boardRef.value?.getCellFromPoint?.(ev.clientX, ev.clientY)
  if (pt) boardRef.value?.setExternalHover?.(pt.x, pt.y)
  else boardRef.value?.setExternalHover?.(null, null)
}
function onUp(ev:MouseEvent){
  if(!isDragging.value) return
  const hover = boardRef.value?.getHoverCell?.()
  const pt = hover ?? boardRef.value?.getCellFromPoint?.(ev.clientX, ev.clientY)
  if (pt && selectedSize.value) {
    const size = selectedSize.value, dir = orientationStr.value
    if (placement.canPlace(pt.x, pt.y, size, dir)) {
      placement.applyShip(pt.x, pt.y, size, dir)
      // auto-clear when this type is complete
      const totalOfSize = uiFleet.value.find(f => f.size === size)?.total ?? 0
      const nowPlaced = placedSizes.value.filter(s => s === size).length
      if (nowPlaced + 1 >= totalOfSize) selectedSize.value = null
    }
  }
  cleanupDrag()
}
function onKeyDuringDrag(e:KeyboardEvent){
  if(!isDragging.value) return
  if(e.key==='r'||e.key==='R'){ e.preventDefault(); placement.orientation.value = placement.orientation.value==='H'?'V':'H'
    if(selectedSize.value){ destroyGhost(); dragGhostEl.value = buildGhost(selectedSize.value, orientationStr.value) } }
  if((e.ctrlKey||e.metaKey) && (e.key==='z'||e.key==='Z')){ e.preventDefault(); placement.removeLast() }
}
function cleanupDrag(){
  isDragging.value=false; document.body.style.userSelect=''
  boardRef.value?.setExternalHover?.(null, null)
  window.removeEventListener('mousemove', onMove)
  window.removeEventListener('keydown', onKeyDuringDrag, {capture:true} as any)
  destroyGhost()
}

// Keyboard (non-drag)
function onKeydown(e:KeyboardEvent){
  if(isDragging.value) return
  if(e.key==='r'||e.key==='R'){ e.preventDefault(); placement.orientation.value = placement.orientation.value==='H'?'V':'H' }
  else if((e.ctrlKey||e.metaKey)&&(e.key==='z'||e.key==='Z')){ e.preventDefault(); placement.removeLast() }
}
onMounted(()=>window.addEventListener('keydown', onKeydown))
onBeforeUnmount(()=>window.removeEventListener('keydown', onKeydown))

// compact list for playing (no names)
const playingItems = computed(() => {
  // create per-instance items for display only
  const countPerSize: Record<number, number> = {}
  for (const f of fleet) countPerSize[f.size] = f.count
  return Object.entries(countPerSize).flatMap(([sizeStr, total]) => {
    const size = Number(sizeStr)
    return Array.from({length: total}, (_,i)=>({ key:`${size}-${i+1}`, size }))
  })
})

// status text
const statusMessage = computed(() => {
  switch (gs.step) {
    case 'join': return 'Gib deinen Namen ein und erstelle oder trete einem Spiel bei.'
    case 'lobby': return 'Warte in der Lobby, bis beide bereit sind.'
    case 'placing': return 'Platziere deine Schiffe auf dem Spielfeld.'
    case 'playing': return gs.gameOver ? 'Spiel beendet.' : (gs.myTurn ? 'Dein Zug!' : 'Gegner am Zug…')
    default: return ''
  }
})

function placeRandomly(){
  placement.randomlyPlaceAll()
  selectedSize.value = null
}

</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-6 sm:p-8 text-slate-200">
    <div class="max-w-[1800px] mx-auto">
      <!-- Header -->
      <div class="mb-8 text-center">
        <div class="mb-2 flex items-center justify-center gap-3">
          <svg class="h-10 w-10 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="5" r="2.5"/><path d="M12 8.5v12"/><path d="M7 11h10"/><path d="M12 20.5c-3.5 0-6-2.5-6-5.5"/><path d="M12 20.5c3.5 0 6-2.5 6-5.5"/>
          </svg>
          <h1 class="text-3xl font-semibold text-blue-400">Schiffeversenken</h1>
        </div>
        <p class="text-slate-400">{{ statusMessage }}</p>
      </div>

      <!-- JOIN / LOBBY -->
      <div v-if="gs.step === 'join'" class="mx-auto max-w-md">
        <div class="rounded-xl border border-slate-700 bg-slate-900/80 p-6 shadow-xl">
          <CreatePanel v-model:name="gs.name" v-model:gameCode="gs.gameCode" @create="gs.createGame" @join="gs.joinGame" />
        </div>
      </div>

      <div v-else-if="gs.step === 'lobby'" class="mx-auto max-w-xl">
        <div class="rounded-xl border border-slate-700 bg-slate-900/80 p-6 shadow-xl">
          <LobbyPanel :gameCode="gs.gameCode" :isReady="gs.isReady" />
        </div>
      </div>

      <!-- PLACING: condensed ShipPalette + your board (single card) -->
      <section v-else-if="gs.step === 'placing'"
               class="mx-auto w-full max-w-[60%] rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl">
        <h3 class="mb-4 text-center text-slate-200">Dein Spielbrett</h3>

        <div class="grid grid-cols-[minmax(220px,280px)_1fr] gap-4">
          <div>
            <ShipPalette
              :fleet="uiFleet"
              :placedSizes="placedSizes"
              :selectedSize="selectedSize ?? null"
              :orientation="orientationStr"
              @pickSize="pickSize"
              @toggleOrientation="placement.orientation.value = placement.orientation.value === 'H' ? 'V' : 'H'"
              @undo="placement.removeLast"
              @reset="() => { placement.reset(); selectedSize = null }"
            />

            <div class="flex flex-col gap-2 mt-2 mb-2">
              <button class="group inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2
                     border-slate-600 bg-slate-800 hover:bg-slate-700 text-slate-200"
                      @click="placeRandomly()" title="Drehen (R)">
                <i class="fa-solid fa-random text-sm"></i>
                <span class="text-xs sm:text-[13px]">Zufällig platzieren</span>
              </button>
            </div>
            <button
              v-if="!placement.allPlaced.value"
              @mousedown.prevent="startPointerDrag"
              class="mt-4 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 transition-colors hover:bg-slate-700 disabled:opacity-50"
              :disabled="!selectedSize"
              :title="selectedSize ? 'Ziehen, dann R zum Drehen' : 'Wähle zuerst einen Schiffstyp'"
            >
              <div class="flex items-center justify-center gap-[6px] my-2">
                <div v-for="i in selectedSize || 0" :key="i" class="h-8 w-8 sm:h-9 sm:w-9 rounded-[10px] bg-blue-400/25" />
              </div>
              Ziehe das nächste Schiff {{}}
            </button>
            
            <button v-else
                    @click="gs.readyUp(placement.placedShips)"
                    class="mt-4 w-full rounded-lg border bg-emerald-600 hover:bg-emerald-20000 border-b-emerald-500 px-3 py-2 transition-colors  disabled:opacity-50"
            >
              Bereit
            </button>
          </div>

          <div class="rounded-lg border border-slate-700/70 bg-slate-900/60 p-4">
            <PlacementBoard
              ref="boardRef"
              :board="placement.board.value"
              :canPlace="placement.canPlace"
              :applyShip="(x:number,y:number,s:number,d:'H'|'V')=>{ placement.applyShip(x,y,s,d); }"
              :nextSize="selectedSize ?? placement.nextSize.value"
              :orientation="placement.orientation.value"
            />
          </div>
        </div>

<!--        <div class="mt-4 flex gap-3">-->
<!--          <button-->
<!--            class="flex-1 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-500 disabled:opacity-50"-->
<!--            :disabled="!placement.allPlaced"-->
<!--            @click="gs.readyUp(placement.placedShips)"-->
<!--          >Spiel starten</button>-->
<!--          <button-->
<!--            class="flex-1 rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 text-slate-200 transition-colors hover:bg-slate-700"-->
<!--            @click="gs.resetForNewGame"-->
<!--          >Reset</button>-->
<!--        </div>-->
      </section>

      <!-- PLAYING: two columns + compact ActiveShips + Statistics -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <!-- ENEMY (always shown) -->
        <section v-if="!showMyBoard" class="lg:col-span-1 h-full rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl">
          <Statistics />
        </section>
        
        <section
          :class="[
        'rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl',
        showMyBoard ? 'lg:col-span-2' : 'lg:col-span-2'
      ]"
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-slate-200">{{gs.enemyName}}</h3>
            <button
              type="button"
              class="rounded-lg border border-slate-600 px-3 py-1 text-sm text-slate-200 hover:bg-slate-800"
              @click="console.log('Not implemented')"
            >
              Fähigkeiten
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-1">
              <EnemyShips :ships="playingItems" :sunk-ships="gs.enemySunkShips" />
            </div>

            <div class="md:col-span-3">
              <div
                :class="[
                  'rounded-lg border bg-slate-900/60 p-4 h-full',
                  gs.gameOver && 'pointer-events-none opacity-60',
                  gs.myTurn ? 'border-emerald-500/50' : 'border-rose-500/50'
                ]"
              >
                <EnemyBoard
                  :enemyBoard="gs.enemyBoard"
                  :disabled="gs.gameOver || !gs.myTurn"
                  @fire="(x, y) => gs.fire(x, y)"
                />
              </div>
            </div>
          </div>
        </section>

        <!-- YOU -->
        <section
          :class="[
        'rounded-xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl h-full',
        showMyBoard ? 'lg:col-span-2' : 'lg:col-span-1'
      ]"
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-slate-200">Deine Schiffe</h3>
            <button
              type="button"
              class="rounded-lg border border-slate-600 px-3 py-1 text-sm text-slate-200 hover:bg-slate-800"
              :aria-pressed="showMyBoard"
              @click="showMyBoard = !showMyBoard"
            >
              {{ showMyBoard ? 'Eigenes Brett ausblenden' : 'Eigenes Brett anzeigen' }}
            </button>
          </div>

          <!-- When hidden, only the ship list is shown; no blank space -->
          <div :class="['grid gap-4', showMyBoard ? 'grid-cols-4' : 'grid-cols-1']">
            <div :class="showMyBoard ? 'md:col-span-1' : ''">
              <ActiveShips :ships="playingItems" :board="gs.myBoard" />
            </div>

            <div v-if="showMyBoard" class="md:col-span-3">
              <div
                :class="{ 'pointer-events-none opacity-60': gs.gameOver }"
                class="rounded-lg border border-slate-700/70 bg-slate-900/60 p-4 h-full"
              >
                <PlacementBoard
                  :board="gs.myBoard"
                  :canPlace="() => false"
                  :applyShip="() => {}"
                  :nextSize="null"
                  :orientation="'H'"
                />
              </div>
            </div>
          </div>
        </section>
        
        
      </div>

      

      <GameOverModal :open="gs.gameOver" :youWon="gs.youWon" :winnerName="gs.winnerName" @close="gs.resetForNewGame" />
    </div>
  </div>
</template>

<style scoped>
select:focus, input:focus, button:focus { outline: none }
</style>
