import { ref, computed } from 'vue';

export type AbilityType = 'plane' | 'comb' | 'splatter';

interface AbilityConfig {
  name: string;
  description: string;
  total: number;
}

const ABILITY_CONFIGS: Record<AbilityType, AbilityConfig> = {
  plane: { name: 'Flugzeug', description: 'Komplette Reihe/Spalte', total: 1 },
  splatter: { name: 'Streuschuss', description: '12 zufällige Felder', total: 2 },
  comb: { name: 'Bombe', description: 'Sprengt großen Bereich', total: 1 }
};

export function useAbilities(boardSize = 12) {
  // Usage tracking
  const usedCounts = ref<Record<AbilityType, number>>({
    plane: 0,
    splatter: 0,
    comb: 0
  });

  // Ghost state
  const activeAbility = ref<AbilityType | null>(null);
  const ghostActive = ref(false);
  const ghostElement = ref<HTMLElement | null>(null);
  const planeAxis = ref<'row' | 'col'>('row');
  const hoverCell = ref<{ x: number; y: number } | null>(null);

  // Computed availability
  const remaining = computed(() => ({
    plane: Math.max(0, ABILITY_CONFIGS.plane.total - usedCounts.value.plane),
    splatter: Math.max(0, ABILITY_CONFIGS.splatter.total - usedCounts.value.splatter),
    comb: Math.max(0, ABILITY_CONFIGS.comb.total - usedCounts.value.comb)
  }));

  const exhausted = computed(() => ({
    plane: remaining.value.plane === 0,
    splatter: remaining.value.splatter === 0,
    comb: remaining.value.comb === 0
  }));

  // Reset on game change
  function reset() {
    usedCounts.value = { plane: 0, splatter: 0, comb: 0 };
    destroyGhost();
  }

  function incrementUsage(type: AbilityType) {
    usedCounts.value[type]++;
  }

  // === Ghost Management ===

  function buildGhost(type: AbilityType, axis?: 'row' | 'col'): HTMLElement {
    const cell = window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32;
    const gap = 6;

    if (type === 'plane') {
      const size = boardSize;
      const dir = axis || 'row';
      const cols = dir === 'row' ? size : 1;
      const rows = dir === 'col' ? size : 1;

      const host = document.createElement('div');
      host.className = 'ability-ghost pointer-events-none';
      Object.assign(host.style, {
        position: 'fixed',
        width: `${cols * cell + (cols - 1) * gap}px`,
        height: `${rows * cell + (rows - 1) * gap}px`
      });

      const grid = document.createElement('div');
      Object.assign(grid.style, {
        display: 'grid',
        gridTemplateColumns: `repeat(${cols},${cell}px)`,
        gridTemplateRows: `repeat(${rows},${cell}px)`,
        gap: `${gap}px`
      });

      const count = dir === 'row' ? cols : rows;
      for (let i = 0; i < count; i++) {
        const sq = document.createElement('div');
        sq.className = 'rounded-[10px] bg-blue-400/25';
        sq.style.width = `${cell}px`;
        sq.style.height = `${cell}px`;
        grid.appendChild(sq);
      }
      host.appendChild(grid);
      document.body.appendChild(host);
      return host;
    }

    if (type === 'comb') {
      const cols = 5, rows = 5;
      const host = document.createElement('div');
      host.className = 'ability-ghost pointer-events-none';
      Object.assign(host.style, {
        position: 'fixed',
        width: `${cols * cell + (cols - 1) * gap}px`,
        height: `${rows * cell + (rows - 1) * gap}px`
      });

      const grid = document.createElement('div');
      Object.assign(grid.style, {
        display: 'grid',
        gridTemplateColumns: `repeat(${cols},${cell}px)`,
        gridTemplateRows: `repeat(${rows},${cell}px)`,
        gap: `${gap}px`
      });

      for (let j = 0; j < rows; j++) {
        for (let i = 0; i < cols; i++) {
          if ((i === 0 || i === 4) && (j === 0 || j === 4)) {
            const empty = document.createElement('div');
            empty.style.width = `${cell}px`;
            empty.style.height = `${cell}px`;
            grid.appendChild(empty);
            continue;
          }
          const sq = document.createElement('div');
          sq.className = 'rounded-[10px] bg-blue-400/25';
          sq.style.width = `${cell}px`;
          sq.style.height = `${cell}px`;
          grid.appendChild(sq);
        }
      }
      host.appendChild(grid);
      document.body.appendChild(host);
      return host;
    }

    // splatter
    const host = document.createElement('div');
    host.className = 'ability-ghost pointer-events-none';
    Object.assign(host.style, { position: 'fixed' });
    const dot = document.createElement('div');
    Object.assign(dot.style, {
      width: `${cell}px`,
      height: `${cell}px`,
      borderRadius: '10px',
      background: 'rgba(96,165,250,.25)'
    });
    host.appendChild(dot);
    document.body.appendChild(host);
    return host;
  }

  function destroyGhost(options?: { keepType?: boolean; keepHover?: boolean }) {
    if (ghostElement.value?.parentNode) {
      ghostElement.value.parentNode.removeChild(ghostElement.value);
    }
    ghostElement.value = null;
    ghostActive.value = false;
    if (!options?.keepType) {
      activeAbility.value = null;
    }
    if (!options?.keepHover) {
      hoverCell.value = null;
    }
  }

  function startGhost(type: AbilityType) {
    destroyGhost();
    activeAbility.value = type;
    ghostActive.value = true;
    ghostElement.value = buildGhost(type, planeAxis.value);
    Object.assign(ghostElement.value.style, { left: '-9999px', top: '-9999px', zIndex: '9999' });
  }

  function rebuildGhost() {
    if (!activeAbility.value) return;
    const type = activeAbility.value;
    const hover = hoverCell.value;
    destroyGhost({ keepType: true, keepHover: true });
    ghostElement.value = buildGhost(type, planeAxis.value);
    ghostActive.value = true;
    activeAbility.value = type;
    if (!hover) hoverCell.value = null;
    Object.assign(ghostElement.value!.style, { zIndex: '9999', position: 'fixed' });
    if (hover && type === 'plane') {
      // plane ghost gets positioned by caller via DOM calculations
    }
  }

  function rotatePlane() {
    planeAxis.value = planeAxis.value === 'row' ? 'col' : 'row';
    rebuildGhost();
  }

  // === Preview Calculation ===

  function computePreview(type: AbilityType, payload: any): Array<{ x: number; y: number }> {
    if (type === 'plane') {
      const axis = payload.axis || 'row';
      const hover = payload.hover;
      if (!hover) return [];
      const idx = axis === 'row' ? hover.y : hover.x;
      if (axis === 'row') {
        return Array.from({ length: boardSize }, (_, x) => ({ x, y: idx }));
      }
      return Array.from({ length: boardSize }, (_, y) => ({ x: idx, y }));
    }

    if (type === 'comb') {
      const center = payload.center;
      if (!center) return [];
      const cells: Array<{ x: number; y: number }> = [];
      for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
          if (Math.abs(dx) === 2 && Math.abs(dy) === 2) continue;
          const x = center.x + dx, y = center.y + dy;
          if (x >= 0 && x < boardSize && y >= 0 && y < boardSize) {
            cells.push({ x, y });
          }
        }
      }
      return cells;
    }

    return []; // splatter has no preview
  }

  function computeCells(type: AbilityType, payload: any): Array<{ x: number; y: number }> {
    if (type === 'plane') {
      const axis = payload.axis || 'row';
      const idx = Math.max(0, Math.min(boardSize - 1, payload.index ?? 0));
      if (axis === 'row') {
        return Array.from({ length: boardSize }, (_, x) => ({ x, y: idx }));
      }
      return Array.from({ length: boardSize }, (_, y) => ({ x: idx, y }));
    }

    if (type === 'comb') {
      const center = payload.center;
      if (!center) return [];
      const cells: Array<{ x: number; y: number }> = [];
      for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
          if (Math.abs(dx) === 2 && Math.abs(dy) === 2) continue;
          const x = center.x + dx, y = center.y + dy;
          if (x >= 0 && x < boardSize && y >= 0 && y < boardSize) {
            cells.push({ x, y });
          }
        }
      }
      return cells;
    }

    // splatter: 12 random
    const total = boardSize * boardSize;
    const picks = 12;
    const chosen = new Set<number>();
    while (chosen.size < Math.min(picks, total)) {
      chosen.add(Math.floor(Math.random() * total));
    }
    return Array.from(chosen).map(n => ({ x: n % boardSize, y: Math.floor(n / boardSize) }));
  }

  return {
    // State
    usedCounts,
    remaining,
    exhausted,
    activeAbility,
    ghostActive,
    ghostElement,
    planeAxis,
    hoverCell,
    configs: ABILITY_CONFIGS,

    // Methods
    reset,
    incrementUsage,
    startGhost,
    destroyGhost,
    rebuildGhost,
    rotatePlane,
    computePreview,
    computeCells
  };
}
