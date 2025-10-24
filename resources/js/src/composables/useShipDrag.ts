import { ref, computed } from 'vue';

export function useShipDrag() {
  const isDragging = ref(false);
  const ghostElement = ref<HTMLElement | null>(null);
  const lastMouse = ref({ x: 0, y: 0 });
  const boardRef = ref<any>(null);

  const cellSize = computed(() =>
    window.matchMedia?.('(min-width: 640px)').matches ? 36 : 32
  );

  function buildShipGhost(size: number, orientation: 'H' | 'V'): HTMLElement {
    const cell = cellSize.value;
    const gap = 6;
    const cols = orientation === 'H' ? size : 1;
    const rows = orientation === 'V' ? size : 1;

    const host = document.createElement('div');
    host.className = 'drag-ghost pointer-events-none';
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

    for (let i = 0; i < size; i++) {
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

  function destroyGhost() {
    if (ghostElement.value?.parentNode) {
      ghostElement.value.parentNode.removeChild(ghostElement.value);
    }
    ghostElement.value = null;
  }

  function moveGhostAt(x: number, y: number) {
    if (!ghostElement.value) return;
    const c = cellSize.value;
    ghostElement.value.style.left = `${x - c / 2}px`;
    ghostElement.value.style.top = `${y - c / 2}px`;
  }

  function startDrag(
    ev: PointerEvent,
    size: number,
    orientation: 'H' | 'V',
    onMove: (x: number, y: number) => void,
    onEnd: (x: number, y: number) => void
  ) {
    // Release capture and blur
    const target = ev.currentTarget as HTMLElement | null;
    try { (target as any)?.releasePointerCapture?.(ev.pointerId); } catch {}
    target?.blur?.();

    destroyGhost();
    ghostElement.value = buildShipGhost(size, orientation);
    Object.assign(ghostElement.value.style, {
      left: '-9999px',
      top: '-9999px',
      zIndex: '9999',
      position: 'fixed'
    });

    isDragging.value = true;
    document.body.style.userSelect = 'none';
    lastMouse.value = { x: ev.clientX, y: ev.clientY };
    moveGhostAt(ev.clientX, ev.clientY);

    const pointerMove = (e: PointerEvent) => {
      if (!isDragging.value) return;
      lastMouse.value = { x: e.clientX, y: e.clientY };
      moveGhostAt(e.clientX, e.clientY);

      const pt = boardRef.value?.getCellFromPoint?.(e.clientX, e.clientY);
      if (pt) onMove(pt.x, pt.y);
      else onMove(-1, -1);
    };

    const pointerUp = (e: PointerEvent) => {
      if (!isDragging.value) return;
      const hover = boardRef.value?.getHoverCell?.();
      const pt = hover ?? boardRef.value?.getCellFromPoint?.(e.clientX, e.clientY);
      if (pt) onEnd(pt.x, pt.y);
      cleanup();
    };

    window.addEventListener('pointermove', pointerMove, { passive: false });
    window.addEventListener('pointerup', pointerUp, { passive: false, once: true });
  }

  function updateGhost(size: number, orientation: 'H' | 'V') {
    destroyGhost();
    ghostElement.value = buildShipGhost(size, orientation);
    Object.assign(ghostElement.value.style, { zIndex: '9999', position: 'fixed' });
    moveGhostAt(lastMouse.value.x, lastMouse.value.y);
  }

  function cleanup() {
    isDragging.value = false;
    document.body.style.userSelect = '';
    boardRef.value?.setExternalHover?.(null, null);
    destroyGhost();
  }

  return {
    isDragging,
    boardRef,
    lastMouse,
    startDrag,
    updateGhost,
    cleanup,
    moveGhostAt
  };
}