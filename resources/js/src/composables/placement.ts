import { ref, computed } from 'vue';
import type { Dir, PlacedShip, ShipSpec } from '@/src/types';

export function usePlacement(boardSize = 12, fleet: ShipSpec[]) {
    const board = ref<number[][]>(Array.from({ length: boardSize }, () => Array(boardSize).fill(0)));
    const palette = fleet.flatMap(s => Array.from({ length: s.count }, () => s.size));
    const nextIndex = ref(0);
    const orientation = ref<Dir>('H');
    const placedShips = ref<PlacedShip[]>([]);

    const inBounds = (x: number, y: number) => x >= 0 && x < boardSize && y >= 0 && y < boardSize;

    function canPlace(x: number, y: number, size: number, dir: Dir, noTouch = true): boolean {
        for (let i = 0; i < size; i++) {
            const cx = dir === 'H' ? x + i : x;
            const cy = dir === 'V' ? y + i : y;
            if (!inBounds(cx, cy)) return false;
            if (board.value[cy][cx] !== 0) return false;
            if (noTouch) {
                for (let dy = -1; dy <= 1; dy++) {
                    for (let dx = -1; dx <= 1; dx++) {
                        const nx = cx + dx, ny = cy + dy;
                        if (inBounds(nx, ny) && board.value[ny][nx] === 1) return false;
                    }
                }
            }
        }
        return true;
    }

    function applyShip(x: number, y: number, size: number, dir: Dir) {
        for (let i = 0; i < size; i++) {
            const cx = dir === 'H' ? x + i : x;
            const cy = dir === 'V' ? y + i : y;
            board.value[cy][cx] = 1;
        }
        placedShips.value.push({ x, y, size, dir });
        nextIndex.value++;
    }

    function removeLast() {
        const last = placedShips.value.pop();
        if (!last) return;
        const { x, y, size, dir } = last;
        for (let i = 0; i < size; i++) {
            const cx = dir === 'H' ? x + i : x;
            const cy = dir === 'V' ? y + i : y;
            board.value[cy][cx] = 0;
        }
        nextIndex.value--;
    }

    function reset() {
        board.value = Array.from({ length: boardSize }, () => Array(boardSize).fill(0));
        placedShips.value = [];
        nextIndex.value = 0;
        orientation.value = 'H';
    }

    const allPlaced = computed(() => nextIndex.value >= palette.length);
    const nextSize = computed(() => palette[nextIndex.value]);

    return { board, canPlace, applyShip, removeLast, reset, allPlaced, nextSize, nextIndex, orientation, placedShips, palette };
}
