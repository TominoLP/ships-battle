<script setup lang="ts">
import { computed, watch } from 'vue'

type ShipTypeItem = { name: string; size: number; total: number }

const props = defineProps<{
  fleet: ShipTypeItem[]
  placedSizes: number[]
  selectedSize?: number | null
  orientation: 'H' | 'V' | string
}>()

const emit = defineEmits<{
  (e: 'pickSize', size: number | null): void
  (e: 'toggleOrientation'): void
  (e: 'undo'): void
  (e: 'reset'): void
}>()

const items = computed(() => {
  const bySize = props.placedSizes.reduce<Record<number, number>>((acc, s) => {
    acc[s] = (acc[s] || 0) + 1
    return acc
  }, {})
  return props.fleet.map(ft => {
    const placed = bySize[ft.size] || 0
    const remaining = Math.max(0, ft.total - placed)
    const complete = remaining === 0
    return { ...ft, placed, remaining, complete }
  })
})

const orientationLabel = computed(() =>
  (props.orientation === 'H' ? 'Horizontal' : 'Vertikal')
)

watch([() => props.placedSizes.slice(), () => props.selectedSize], () => {
  const all = items.value
  const anyAvailable = all.find(i => !i.complete)
  if (props.selectedSize == null) { emit('pickSize', anyAvailable ? anyAvailable.size : null); return }
  const selected = all.find(i => i.size === props.selectedSize)
  if (!selected) { emit('pickSize', anyAvailable ? anyAvailable.size : null); return }
  if (selected.complete) {
    const sorted = [...all].sort((a, b) => a.size - b.size)
    const idx = sorted.findIndex(i => i.size === selected.size)
    const next = sorted.slice(idx + 1).find(i => !i.complete) ?? sorted.slice(0, idx).find(i => !i.complete)
    emit('pickSize', next ? next.size : null)
  }
}, { immediate: true })
</script>

<template>
  <div class="space-y-3">
   

    <div class="space-y-3">
      <button
        v-for="it in items" :key="it.size" type="button"
        class="w-full text-left rounded-xl border px-3 py-3 transition bg-slate-900/60 border-slate-700/70 hover:bg-slate-900/80"
        :class="[ it.complete ? 'ring-1 ring-emerald-600/30' : (selectedSize === it.size ? 'ring-1 ring-blue-500/50 bg-slate-900/80' : '') ]"
        @click="!it.complete && $emit('pickSize', it.size)"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm text-slate-200 font-medium">{{ it.name }}</div>
          <div class="flex items-center gap-2">
            <span v-if="!it.complete"
                  class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                         bg-slate-800/70 text-slate-300 border-slate-600/60"
                  title="verbleibend / gesamt">
              {{ it.total - it.placed }}/{{ it.total }}
            </span>
            <span v-if="it.complete"
                  class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                         bg-emerald-600/15 text-emerald-300 border-emerald-500/40">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <path d="M20 6L9 17l-5-5"/>
              </svg>
              Alle platziert
            </span>
          </div>
        </div>

        <div
          class="rounded-lg border p-2"
          :class="it.complete
            ? 'border-emerald-600/40 bg-emerald-600/10'
            : (selectedSize === it.size ? 'border-blue-500/50 bg-blue-500/5' : 'border-slate-700/60 bg-slate-800/60')"
        >
          <div class="flex items-center gap-1.5">
            <div v-for="i in it.size" :key="i"
                 class="h-4 flex-1 rounded-md border transition"
                 :class="it.complete
                    ? 'border-emerald-500/50 bg-emerald-500/80 shadow-[inset_0_0_0_1px_rgba(16,185,129,.3),0_6px_14px_-6px_rgba(16,185,129,.45)]'
                    : (selectedSize === it.size ? 'border-blue-400/60 bg-blue-500/50' : 'border-slate-600 bg-slate-700')" />
          </div>
        </div>
      </button>
    </div>

    <div class="flex flex-col gap-2 mt-5">
      <button class="group inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2
                     border-slate-600 bg-slate-800 hover:bg-slate-700 text-slate-200"
              @click="$emit('toggleOrientation')" title="Drehen (R)">
        <i class="fa-solid fa-arrows-rotate text-sm transition-transform group-hover:rotate-90"></i>
        <span class="text-xs sm:text-[13px]">{{ orientationLabel }}</span>
        <span class="hidden sm:inline text-[10px] opacity-60">(R)</span>
      </button>

      <button class="inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2
                     border-slate-600 bg-slate-800 hover:bg-slate-700 text-slate-200"
              @click="$emit('undo')" title="Zurück (Strg+Z)">
        <i class="fa-solid fa-rotate-left text-sm"></i>
        <span class="text-xs sm:text-[13px]">Zurück</span>
        <span class="hidden sm:inline text-[10px] opacity-60">(Strg+Z)</span>
      </button>
    </div>
  </div>
</template>
