<script lang="ts" setup>
import { computed } from 'vue';

type Ship = { key: string | number; size: number }

const props = defineProps<{
  ships: Ship[]
  sunkShips: number[]
}>();

const shipsWithStatus = computed(() => {
  const counts = props.sunkShips.reduce<Record<number, number>>((m, s) => {
    m[s] = (m[s] ?? 0) + 1;
    return m;
  }, {});

  return props.ships.map((ship) => {
    const remaining = counts[ship.size] ?? 0;
    const sunk = remaining > 0;
    if (sunk) counts[ship.size] = remaining - 1;
    return { ...ship, sunk };
  });
});
</script>

<template>
  <div class="grid grid-cols-1 gap-2">
    <div
      v-for="ship in shipsWithStatus"
      :key="ship.key"
      class="rounded-lg border border-slate-700/70 bg-slate-900/60 p-2"
    >
      <div class="flex items-center gap-1.5">
        <div
          v-for="i in ship.size"
          :key="i"
          :class="ship.sunk
            ? 'border-rose-500/50 bg-rose-500/80 shadow-[inset_0_0_0_1px_rgba(244,63,94,.35),0_6px_14px_-6px_rgba(244,63,94,.5)]'
            : 'bg-slate-700'"
          class="h-4 flex-1 rounded-md border transition"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>
