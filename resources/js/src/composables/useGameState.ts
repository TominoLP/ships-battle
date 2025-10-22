import { ref, unref, watch } from 'vue';
import GameController from '@/actions/App/Http/Controllers/GameController';
import { api } from '@/src/composables/useApi';
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
  const enemySunkShips = ref<number[]>([]);
  const enemyName = ref('');

  const gameOver = ref(false);
  const youWon = ref<boolean | null>(null);
  const winnerName = ref('');

  const socket = ref<any>(null);

  function pushMsg(msg: string) {
    messages.value.unshift(`[${new Date().toLocaleTimeString()}] ${msg}`);
  }

  async function createGame() {
    const data = await api<{ game_code: string; game_id: number; player_id: number }>(
      GameController.create.post(),
      { name: name.value }
    );
    gameCode.value = data.game_code;
    gameId.value = data.game_id;
    playerId.value = data.player_id;
    initSocket();
    step.value = 'lobby';
  }

  async function joinGame() {
    const data = await api<{ game_id: number; player_id: number }>(
      GameController.join.post(),
      { name: name.value, code: gameCode.value }
    );
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

    watch(socket.value.messages, (list: string[]) => {
      messages.value = list;
    });

    socket.value.on('game_finished', ({ winner }: any) => {
      const won = playerId.value === winner.id;
      youWon.value = won;
      winnerName.value = winner.name;
      gameOver.value = true;
      pushMsg(won ? 'You won.' : `You lost. Winner: ${winner.name}`);
    });

    socket.value.on('player_joined', ({ player }: any) => {
      pushMsg(`${player.name} joined the game`);
      step.value = 'placing';
    });

    socket.value.on('player_ready', ({ player }: any) => {
      pushMsg(`${player.name} is ready`);
    });

    socket.value.on('turn_changed', ({ player }: any) => {
      pushMsg(`Turn: ${player.name}`);
      myTurn.value = player.id === playerId.value;
    });

    /*
    * 0 = empty water (unknown to enemy)
    * 1 = your ship placed
    * 3 = enemy shot missed here
    * 4 = enemy hit your ship here
     */
    socket.value.on('shot_fired', (data: any) => {
      pushMsg(`${data.player.name} shot (${data.x},${data.y}) – ${data.result}`);
      if (data.player.id !== playerId.value) {
        const x = data.x;
        const y = data.y;
        const cell = myBoard.value[y]?.[x];
        const isHit = data.result === 'hit' || data.result === 'sunk' || data.result === 'kill';

        if (isHit) {
          if (cell === 1 || cell === 0 || cell === 3) {
            myBoard.value[y][x] = 4;
          }
        } else {
          if (cell === 0 || cell === 1) {
            myBoard.value[y][x] = cell === 1 ? 1 : 3;
          }
        }
      }
    });

    socket.value.on('ship_sunk', ({ size, player }: any) => {
      if (player.id !== playerId.value) return;
      pushMsg(`${player.name} sunk a ship of size ${size}`);
      enemySunkShips.value.push(size);
    });

    socket.value.on('game_started', ({ current, players }: any) => {
      pushMsg('Game started');
      step.value = 'playing';
      enemyName.value = players.find((p: any) => p.id !== playerId.value)?.name || '';
      myTurn.value = current?.id === playerId.value;
    });
  }

  async function readyUp(ships: PlacedShip[] | any) {
    if (!playerId.value) return;
    const plainShips = unref(ships).map((s: PlacedShip) => ({ x: s.x, y: s.y, size: s.size, dir: s.dir }));
    const data = await api<{ started: boolean }>(
      GameController.placeShips.post(),
      { player_id: playerId.value, ships: plainShips }
    );
    isReady.value = true;
    step.value = data.started ? 'playing' : 'lobby';
  }

  async function fire(x: number, y: number) {
    if (gameOver.value) return;
    if (step.value !== 'playing' || !playerId.value) return;
    if (!myTurn.value) {
      pushMsg('Not your turn');
      return;
    }
    try {
      const data = await api<{ result: 'hit' | 'miss' | 'sunk' | string }>(
        GameController.shoot.post(),
        { player_id: playerId.value, x, y }
      );
      enemyBoard.value[y][x] = (data.result === 'hit' || data.result === 'sunk') ? 2 : 1;
      pushMsg(`You fired at (${x},${y}) – ${data.result}`);
    } catch (e: any) {
      if (e?.response?.status === 409) pushMsg('Not your turn');
      else pushMsg('Shot failed');
    }
  }

  return {
    step, name, gameCode, gameId, playerId, isReady, myTurn, messages,
    myBoard, enemyBoard, enemyName, enemySunkShips, gameOver, youWon, winnerName,
    createGame, joinGame, resetForNewGame, readyUp, fire,
    pushMsg
  };
}
