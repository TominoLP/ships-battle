import { ref, unref, watch } from 'vue';
import GameController from '@/actions/App/Http/Controllers/GameController';
import { api } from '@/src/composables/useApi';
import type { PlacedShip, Step } from '@/src/types';
import { useGameSocket } from '@/src/composables/useGameSocket';

type RematchMapping = {
  old_player_id: number;
  new_player_id: number;
  name: string;
  user_id: number | null;
  is_turn: boolean;
};

type RematchResponse = {
  status: 'waiting' | 'ready';
  message?: string;
  game?: { id: number; code: string };
  player?: RematchMapping;
  players?: RematchMapping[];
};

type RematchEventPayload = {
  next?: { id: number; code: string };
  players?: RematchMapping[];
};

const SESSION_STORAGE_KEY = 'ships-battle/session/v1';
const RESUMABLE_STEPS: Step[] = ['lobby', 'placing', 'playing'];

export function useGameState() {
  const step = ref<Step>('join');
  const gameCode = ref('');
  const gameId = ref<number | null>(null);
  const playerId = ref<number | null>(null);
  const isReady = ref(false);
  const myTurn = ref(false);
  const messages = ref<string[]>([]);

  const createEmptyBoard = () => Array.from({ length: 12 }, () => Array(12).fill(0));

  const myBoard = ref<number[][]>(createEmptyBoard());
  const enemyBoard = ref<number[][]>(createEmptyBoard());
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

  // === Rematch flow ===
  const rematchState = ref<'idle' | 'waiting' | 'ready'>('idle');
  const rematchError = ref<string | null>(null);
  let restoringSession = false;

  if (typeof window !== 'undefined') {
    const initialCode = new URLSearchParams(window.location.search).get('code');
    if (initialCode) {
      gameCode.value = initialCode;
    }
  }

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

  function clearSession() {
    if (typeof window === 'undefined') return;
    try {
      window.localStorage.removeItem(SESSION_STORAGE_KEY);
    } catch (err) {
      console.warn('[GameState] Failed to clear session storage', err);
    }
  }

  function persistSession() {
    if (typeof window === 'undefined') return;
    if (restoringSession) return;
    if (!playerId.value || !gameId.value || !gameCode.value) {
      clearSession();
      return;
    }
    if (!RESUMABLE_STEPS.includes(step.value) || gameOver.value) {
      clearSession();
      return;
    }
    const payload = {
      playerId: playerId.value,
      gameId: gameId.value,
      gameCode: gameCode.value,
      step: step.value,
    };
    try {
      window.localStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify(payload));
    } catch (err) {
      console.warn('[GameState] Failed to persist session', err);
    }
  }

  async function restoreSession() {
    if (typeof window === 'undefined') return;
    let payload: { playerId: number; gameId: number; gameCode: string; step: Step } | null = null;
    try {
      const raw = window.localStorage.getItem(SESSION_STORAGE_KEY);
      if (!raw) return;
      const parsed = JSON.parse(raw);
      if (
        typeof parsed?.playerId === 'number' &&
        typeof parsed?.gameId === 'number' &&
        typeof parsed?.gameCode === 'string'
      ) {
        const storedStep = parsed.step as Step;
        payload = {
          playerId: parsed.playerId,
          gameId: parsed.gameId,
          gameCode: parsed.gameCode,
          step: RESUMABLE_STEPS.includes(storedStep) ? storedStep : 'placing',
        };
      }
    } catch (err) {
      console.warn('[GameState] Failed to parse session payload', err);
    }

    if (!payload) return;

    restoringSession = true;
    try {
      playerId.value = payload.playerId;
      gameId.value = payload.gameId;
      gameCode.value = payload.gameCode;
      step.value = payload.step;
      initSocket();
      await refreshState(payload.playerId);
    } catch (err) {
      console.error('[GameState] Failed to restore session', err);
      resetForNewGame();
      clearSession();
    } finally {
      restoringSession = false;
      persistSession();
    }
  }

  function updateGameUrl(stepValue: Step, code: string | null | undefined) {
    if (typeof window === 'undefined') return;
    const active = stepValue === 'lobby' || stepValue === 'placing' || stepValue === 'playing';
    const basePath = '/game';
    const target = active && code
      ? `${basePath}?code=${encodeURIComponent(code)}`
      : basePath;
    const current = `${window.location.pathname}${window.location.search}`;
    if (current !== target) {
      window.history.replaceState({}, '', target);
    }
  }

  watch([step, gameCode], ([stepValue, code]) => {
    updateGameUrl(stepValue, code);
  }, { immediate: true });

  watch([playerId, gameId, gameCode, step, gameOver], () => {
    persistSession();
  });

  if (typeof window !== 'undefined') {
    void restoreSession();
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
        game?: { id: number; code: string; status: string; winner_player_id: number | null } | null;
        winner?: { id: number; name: string } | null;
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

      if (data?.game) {
        if (!gameId.value) {
          gameId.value = data.game.id ?? null;
        }
        if (!gameCode.value) {
          gameCode.value = data.game.code ?? '';
        }
        const status = data.game.status;
        const playerReady = data?.player?.isReady ?? false;
        if (status === 'completed') {
          gameOver.value = true;
          youWon.value = data?.winner?.id === playerId.value;
          winnerName.value = data?.winner?.name ?? '';
          step.value = 'playing';
        } else {
          gameOver.value = false;
          winnerName.value = '';
          youWon.value = null;
          if (status === 'in_progress') {
            step.value = 'playing';
          } else if (status === 'creating') {
            step.value = playerReady ? 'lobby' : 'placing';
          } else if (status === 'waiting') {
            step.value = 'lobby';
          }
        }
      } else {
      }
    } catch (err) {
      console.error('[GameState] Failed to refresh state', err);
    }
  }

  async function createGame() {
    const data = await api<{ game_code: string; game_id: number; player_id: number }>(
      GameController.create.post()
    );
    gameCode.value = data.game_code;
    gameId.value = data.game_id;
    playerId.value = data.player_id;
    initSocket();
    await refreshState(data.player_id);
    step.value = 'lobby';
    rematchState.value = 'idle';
    rematchError.value = null;
  }

  async function joinGame() {
    const data = await api<{ game_id: number; player_id: number }>(
      GameController.join.post(),
      { code: gameCode.value }
    );
    gameId.value = data.game_id;
    playerId.value = data.player_id;
    initSocket();
    await refreshState(data.player_id);
    step.value = 'placing';
    rematchState.value = 'idle';
    rematchError.value = null;
  }

  function resetForNewGame() {
    if (socket.value) {
      try {
        socket.value.leave?.();
        socket.value.disconnect?.();
      } catch (err) {
        console.warn('[GameState] Failed to disconnect socket', err);
      } finally {
        socket.value = null;
      }
    }
    step.value = 'join';
    gameOver.value = false;
    youWon.value = null;
    winnerName.value = '';
    isReady.value = false;
    enemyBoard.value = createEmptyBoard();
    myBoard.value = createEmptyBoard();
    enemySunkShips.value = [];
    abilityUsage.value = { ...DEFAULT_ABILITY_USAGE };
    turnKills.value = 0;
    rematchState.value = 'idle';
    rematchError.value = null;
    messages.value = [];
    gameCode.value = '';
    gameId.value = null;
    playerId.value = null;
    clearSession();
  }

  function applyRematch(nextGame: { id: number; code: string }, mapping: RematchMapping) {
    if (!nextGame?.id || !nextGame?.code || !mapping?.new_player_id) {
      return;
    }

    if (socket.value) {
      try {
        socket.value.leave?.();
      } catch (err) {
        console.warn('[GameState] Failed to leave previous channel', err);
      }
    }

    socket.value = null;

    messages.value = [];
    myBoard.value = createEmptyBoard();
    enemyBoard.value = createEmptyBoard();
    enemySunkShips.value = [];
    abilityUsage.value = { ...DEFAULT_ABILITY_USAGE };
    turnKills.value = 0;
    gameOver.value = false;
    youWon.value = null;
    winnerName.value = '';
    rematchState.value = 'idle';
    rematchError.value = null;

    gameId.value = nextGame.id;
    gameCode.value = nextGame.code;
    playerId.value = mapping.new_player_id;
    myTurn.value = Boolean(mapping.is_turn);
    isReady.value = false;
    step.value = 'placing';

    pushMsg('Rematch gestartet – neues Spiel bereit');

    initSocket();
    void refreshState(mapping.new_player_id);
  }

  function handleRematchReady(payload: RematchEventPayload) {
    if (!payload?.players || !Array.isArray(payload.players)) {
      return;
    }
    const mapping = payload.players.find((entry) => entry.old_player_id === playerId.value);
    if (!mapping) return;
    if (!payload.next?.id || !payload.next?.code) return;
    pushMsg('Rematch angenommen – wechsel zum neuen Spiel');
    applyRematch(payload.next, mapping);
  }

  async function requestRematch() {
    if (!playerId.value) return;
    rematchError.value = null;
    rematchState.value = 'waiting';

    try {
      const data = await api<RematchResponse>(
        GameController.rematch.post(),
        { player_id: playerId.value }
      );

      if (data.status === 'waiting') {
        rematchState.value = 'waiting';
        pushMsg('Rematch angefragt – warte auf Gegner...');
        return;
      }

      if (data.status === 'ready' && data.game) {
        rematchState.value = 'ready';
        pushMsg('Rematch bestätigt – neues Spiel startet');
        if (Array.isArray(data.players) && data.players.length > 0) {
          handleRematchReady({
            next: data.game,
            players: data.players
          });
        } else if (data.player) {
          applyRematch(data.game, data.player);
        }
        return;
      }

      rematchState.value = 'idle';
    } catch (e: any) {
      rematchState.value = 'idle';
      let message = 'Rematch fehlgeschlagen';
      const resp: Response | undefined = e?.response;
      if (resp) {
        try {
          const json = await resp.clone().json();
          message = (json?.error as string) ?? message;
        } catch (_) {
          try {
            message = await resp.clone().text();
          } catch (_) {
            message = e?.message ?? message;
          }
        }
      } else if (e?.message) {
        message = e.message;
      }
      rematchError.value = message;
      pushMsg(message);
    }
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
      rematchState.value = 'idle';
      rematchError.value = null;
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

    socket.value.on('rematch_ready', (payload: RematchEventPayload) => {
      handleRematchReady(payload);
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
    step, gameCode, gameId, playerId, isReady, myTurn, messages,
    myBoard, enemyBoard, enemyName, enemySunkShips, gameOver, youWon, winnerName,
    abilityUsage, turnKills, rematchState, rematchError,
    createGame, joinGame, resetForNewGame, readyUp, fire, useAbility, requestRematch,
    pushMsg
  };
}
