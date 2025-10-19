import { ref, watch, unref } from 'vue';
import { apiCreateGame, apiJoinGame, apiPlaceShips, apiShoot } from '@/src/composables/useApi';
import type { PlacedShip, Step } from '@/src/types';
import { useGameSocket } from '@/src/composables/useGameSocket';

export function useGameState() {
    const step = ref<Step>('join');
    const name = ref('');
    const gameCode = ref('');
    const gameId = ref<number | null>(null);
    const playerId = ref<number | null>(null);
    const isReady = ref(false);
    const myTurn = ref(false);
    const messages = ref<string[]>([]);

    const myBoard = ref<number[][]>(Array.from({ length: 12 }, () => Array(12).fill(0)));
    const enemyBoard = ref<number[][]>(Array.from({ length: 12 }, () => Array(12).fill(0)));

    const gameOver = ref(false);
    const youWon = ref<boolean | null>(null);
    const winnerName = ref('');

    const socket = ref<any>(null);

    function pushMsg(msg: string) {
        messages.value.unshift(`[${new Date().toLocaleTimeString()}] ${msg}`);
    }

    async function createGame() {
        const data = await apiCreateGame(name.value);
        gameCode.value = data.game_code;
        gameId.value = data.game_id;
        playerId.value = data.player_id;
        initSocket();
        step.value = 'lobby';
    }

    async function joinGame() {
        const data = await apiJoinGame(name.value, gameCode.value);
        gameId.value = data.game_id;
        playerId.value = data.player_id;
        initSocket();
        step.value = 'placing';
    }

    function resetForNewGame() {
        step.value = 'join';
        gameOver.value = false;
        youWon.value = null;
        winnerName.value = '';
        isReady.value = false;
        enemyBoard.value = Array.from({ length: 12 }, () => Array(12).fill(0));
    }

    function initSocket() {
        if (!gameId.value) return;
        socket.value = useGameSocket(gameId.value);

        watch(socket.value.messages, (list: string[]) => { messages.value = list; });

        socket.value.on('game_finished', ({ winner }: any) => {
            const won = playerId.value === winner.id;
            youWon.value = won;
            winnerName.value = winner.name;
            gameOver.value = true;
            pushMsg(won ? '🏆 You won!' : `😞 You lost. Winner: ${winner.name}`);
        });

        socket.value.on('player_joined', ({ player }: any) => {
            pushMsg(`👋 ${player.name} joined the game`);
            step.value = 'placing';
        });

        socket.value.on('player_ready', ({ player }: any) => { pushMsg(`✅ ${player.name} is ready`); });

        socket.value.on('turn_changed', ({ player }: any) => {
            pushMsg(`🔄 Turn: ${player.name}`);
            myTurn.value = player.id === playerId.value;
        });

        socket.value.on('shot_fired', (data: any) => {
            pushMsg(`💥 ${data.player.name} shot (${data.x},${data.y}) – ${data.result}`);
        });

        socket.value.on('game_started', ({ current }: any) => {
            pushMsg('🚀 Game started!');
            step.value = 'playing';
            myTurn.value = current?.id === playerId.value;
        });
    }

    async function readyUp(ships: PlacedShip[] | any) {
        if (!playerId.value) return;
        const plainShips = unref(ships).map((s: PlacedShip) => ({
            x: s.x, y: s.y, size: s.size, dir: s.dir
        }));
        const data = await apiPlaceShips(playerId.value, plainShips);
        isReady.value = true;
        step.value = data.started ? 'playing' : 'lobby';
    }

    async function fire(x: number, y: number) {
        if (gameOver.value) return;
        if (step.value !== 'playing' || !playerId.value) return;
        if (!myTurn.value) { pushMsg('⏳ Not your turn'); return; }
        try {
            const data = await apiShoot(playerId.value, x, y);
            enemyBoard.value[y][x] = (data.result === 'hit' || data.result === 'sunk') ? 2 : 1;
            pushMsg(`🎯 You fired at (${x},${y}) – ${data.result}`);
        } catch (e: any) {
            if (e?.response?.status === 409) pushMsg('⏳ Not your turn');
            else pushMsg('⚠️ Shot failed');
        }
    }

    return {
        step, name, gameCode, gameId, playerId, isReady, myTurn, messages,
        myBoard, enemyBoard, gameOver, youWon, winnerName,
        createGame, joinGame, resetForNewGame, readyUp, fire,
        pushMsg
    };
}
