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

  // === NEW: Ability tracking ===
  const DEFAULT_ABILITY_USAGE = { plane: 0, splatter: 0, comb: 0 } as const;
  const abilityUsage = ref({ ...DEFAULT_ABILITY_USAGE });
  const lastGameCode = ref<string | null>(null);

  // === NEW: Kill tracking ===
  const turnKills = ref(0);

  // Reset ability usage on game change
  watch(() => gameCode.value, (code) => {
    if (code && code !== lastGameCode.value) {
      abilityUsage.value = { ...DEFAULT_ABILITY_USAGE };
      lastGameCode.value = code;
      turnKills.value = 0;
    }
  });

  function pushMsg(msg: string) {
    messages.value.unshift(`[${new Date().toLocaleTimeString()}] ${msg}`);
  }

  async function refreshState(targetPlayerId?: number) {
    const id = targetPlayerId ?? playerId.value;
    if (!id) return;

    try {
      const data = await api<{
        player: {
          board: number[][];
          isTurn: boolean;
          isReady: boolean;
          abilityUsage: typeof DEFAULT_ABILITY_USAGE;
          turnKills: number;
        };
        enemy: { id: number; name: string; isReady: boolean } | null;
      }>(GameController.state.get(id));

      if (data?.player) {
        myBoard.value = data.player.board ?? myBoard.value;
        myTurn.value = data.player.isTurn ?? myTurn.value;
        isReady.value = data.player.isReady ?? isReady.value;
        abilityUsage.value = data.player.abilityUsage ?? { ...DEFAULT_ABILITY_USAGE };
        turnKills.value = data.player.turnKills ?? 0;
      }

      if (data?.enemy) {
        enemyName.value = data.enemy.name ?? enemyName.value;
      }
    } catch (err) {
      console.error('[GameState] Failed to refresh state', err);
    }
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
    await refreshState(data.player_id);
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
    await refreshState(data.player_id);
    step.value = 'placing';
  }

  function resetForNewGame() {
    step.value = 'join';
    gameOver.value = false;
    youWon.value = null;
    winnerName.value = '';
    isReady.value = false;
    enemyBoard.value = Array.from({ length: 12 }, () => Array(12).fill(0));
    abilityUsage.value = { ...DEFAULT_ABILITY_USAGE };
    turnKills.value = 0;
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
      turnKills.value = 0;
    });

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
      void refreshState(playerId.value ?? undefined);
    });
  }

  async function readyUp(ships: PlacedShip[] | any) {
    if (!playerId.value) return;
    const plainShips = unref(ships).map((s: PlacedShip) => ({
      x: s.x, y: s.y, size: s.size, dir: s.dir
    }));
    const data = await api<{ started: boolean }>(
      GameController.placeShips.post(),
      { player_id: playerId.value, ships: plainShips }
    );
    isReady.value = true;
    await refreshState();
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
      const data = await api<{
        shots: Array<{ x: number; y: number; result: string }>;
        sunk?: Array<{ size: number }>;
        gameOver: boolean;
        winner?: { id: number; name: string };
        abilityUsage?: typeof DEFAULT_ABILITY_USAGE;
        turnKills?: number;
      }>(
        GameController.shoot.post(),
        { player_id: playerId.value, x, y }
      );

      const shot = data.shots?.[0];
      if (shot) {
        const result = shot.result.toLowerCase();
        if (result === 'hit' || result === 'sunk') {
          enemyBoard.value[shot.y][shot.x] = 2;
        } else if (result === 'miss') {
          enemyBoard.value[shot.y][shot.x] = 1;
        }
        pushMsg(`You fired at (${shot.x},${shot.y}) – ${result}`);
        if (result === 'miss') {
          myTurn.value = false;
        }
      }

      if (data.abilityUsage) {
        abilityUsage.value = data.abilityUsage;
      }
      if (typeof data.turnKills === 'number') {
        turnKills.value = data.turnKills;
      }

      if (data.gameOver) {
        gameOver.value = true;
        if (data.winner) {
          youWon.value = data.winner.id === playerId.value;
          winnerName.value = data.winner.name;
        }
      }
    } catch (e: any) {
      const resp: Response | undefined = e?.response;
      if (resp) {
        let message: string | null = null;
        try {
          const json = await resp.clone().json();
          message = json?.error ?? null;
        } catch (_) {
          try {
            message = await resp.clone().text();
          } catch (_) {
            message = null;
          }
        }
        pushMsg(message || (resp.status === 409 ? 'Not your turn' : 'Shot failed'));
      } else {
        pushMsg('Shot failed');
      }
    }
  }

  // === NEW: Enhanced Ability with tracking ===
  async function useAbility(
    type: 'plane' | 'comb' | 'splatter',
    payload: any
  ) {
    if (gameOver.value) return;
    if (step.value !== 'playing' || !playerId.value) return;
    if (!myTurn.value) {
      pushMsg('Not your turn');
      return;
    }

    try {
      const data = await api<{
        shots: Array<{ x: number; y: number; result: 'hit' | 'miss' | 'already' | 'sunk' }>;
        sunk?: Array<{ size: number; cells: number[][] }>;
        gameOver: boolean;
        winner?: { id: number; name: string };
        abilityUsage?: typeof DEFAULT_ABILITY_USAGE;
        turnKills?: number;
      }>(
        GameController.useAbility.post(),
        { player_id: playerId.value, type, payload }
      );

      let anyHit = false;
      for (const s of data.shots) {
        const { x, y, result } = s;
        if (result === 'hit' || result === 'sunk') {
          enemyBoard.value[y][x] = 2;
          anyHit = true;
        } else if (result === 'miss') {
          enemyBoard.value[y][x] = 1;
        }
        if (result === 'sunk') {
          anyHit = true;
        }
      }

      if (data.abilityUsage) {
        abilityUsage.value = data.abilityUsage;
      }
      if (typeof data.turnKills === 'number') {
        turnKills.value = data.turnKills;
      }

      const hits = data.shots.filter(s => s.result === 'hit' || s.result === 'sunk').length;
      const msg = type === 'plane'
        ? `Ability: plane ${payload.axis} ${payload.index}`
        : type === 'comb'
          ? `Ability: comb @ (${payload.center?.x},${payload.center?.y})`
          : 'Ability: splatter';
      pushMsg(`${msg} → ${hits} hit(s)`);

      if (!anyHit) {
        myTurn.value = false;
      }

      if (data.gameOver && data.winner) {
        youWon.value = data.winner.id === playerId.value;
        winnerName.value = data.winner.name;
        gameOver.value = true;
      }
    } catch (e: any) {
      const resp: Response | undefined = e?.response;
      if (resp) {
        let message: string | null = null;
        try {
          const json = await resp.clone().json();
          message = json?.error ?? null;
        } catch (_) {
          try {
            message = await resp.clone().text();
          } catch (_) {
            message = null;
          }
        }
        if (message) {
          pushMsg(message);
        } else if (resp.status === 409) {
          pushMsg('Not your turn');
        } else if (resp.status === 422) {
          pushMsg('Ability input invalid');
        } else {
          pushMsg('Ability failed');
        }
      } else {
        pushMsg('Ability failed');
      }
    }
  }

  return {
    step, name, gameCode, gameId, playerId, isReady, myTurn, messages,
    myBoard, enemyBoard, enemyName, enemySunkShips, gameOver, youWon, winnerName,
    abilityUsage, turnKills,
    createGame, joinGame, resetForNewGame, readyUp, fire, useAbility,
    pushMsg
  };
}
