<?php

namespace App\Http\Controllers;

use App\Events\GameAborted;
use App\Events\GameCreated;
use App\Events\PlayerJoined;
use App\Events\PlayerReady;
use App\Events\PublicGameAvailable;
use App\Events\PublicGameUnavailable;
use App\Events\RematchReady;
use App\Models\Game;
use App\Models\Player;
use App\Services\BattleService;
use App\Services\PlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GameController extends Controller
{
    public function __construct(
        private readonly PlacementService $placementService,
        private readonly BattleService $battleService
    ) {
    }

    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|exists:games,code',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $game = Game::where('code', $request->code)->where('status', '!=', Game::STATUS_COMPLETED)->first();
        if(!$game) {
            $game = Game::where('code', $request->code);
            $game->delete();
            return response()->json(['error' => 'Game not found or already completed'], 404);
        }
        
        $name = trim((string)($user->name ?? ''));
        if ($name === '') {
            $name = 'Player ' . $user->id;
        }

        $existing = $game->players()
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->name !== $name) {
                $existing->update(['name' => $name]);
            }

            return response()->json([
                'player_id' => $existing->id,
                'game_id' => $game->id,
            ]);
        }

        if ($game->players()->count() >= 2) {
            return response()->json(['error' => 'Game is full'], 400);
        }

        $player = Player::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'name' => $name,
            'is_turn' => false,
        ]);

        $game->update(['status' => Game::STATUS_CREATING]);

        broadcast(new PlayerJoined($game, $player));

        return response()->json([
            'player_id' => $player->id,
            'game_id' => $game->id,
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'public' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $name = trim((string)($user->name ?? ''));
        if ($name === '') {
            $name = 'Player ' . $user->id;
        }

        $game = new Game();
        $game->public = $request->boolean('public', false);
        $game->save();
        $player = Player::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'name' => $name,
            'is_turn' => true,
        ]);

        broadcast(new GameCreated($game, $player));
        if ($game->public) {
            broadcast(new PublicGameAvailable($game));
        }

        return response()->json([
            'game_code' => $game->code,
            'player_id' => $player->id,
            'game_id' => $game->id,
            'public' => $game->public,
        ]);
    }

    public function placeShips(Request $request): JsonResponse
    {
        $request->validate([
            'player_id' => 'required|integer|exists:players,id',
            'ships' => 'required|array',
            'ships.*.x' => 'required|integer|min:0|max:11',
            'ships.*.y' => 'required|integer|min:0|max:11',
            'ships.*.size' => 'required|integer|in:2,3,4,5',
            'ships.*.dir' => 'required|string|in:H,V',
        ]);

        /** @var Player $player */
        $player = Player::findOrFail($request->player_id);

        $userId = $request->user()?->id;
        if (!$userId || $player->user_id !== $userId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $ships = collect($request->ships)->map(fn($s) => [
            'x' => (int)$s['x'],
            'y' => (int)$s['y'],
            'size' => (int)$s['size'],
            'dir' => (string)$s['dir'],
        ]);

        try {
            $board = $this->placementService->validateFleet($ships);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $player->update([
            'board' => $board,
            'ships' => $ships->values()->toArray(),
            'is_ready' => true,
        ]);

        broadcast(new PlayerReady($player));

        $game = $player->game;
        $started = false;

        if ($game->players()->count() === 2 && $game->players()->where('is_ready', true)->count() === 2) {
            $players = $game->players()->orderBy('id')->get();
            if ($players->where('is_turn', true)->count() !== 1) {
                $game->players()->update(['is_turn' => false]);
                $starter = $players->first();
                $starter->update(['is_turn' => true, 'turn_kills' => 0]);
            }
            $game->update(['status' => Game::STATUS_IN_PROGRESS]);
            $current = $game->players()->where('is_turn', true)->first();
            broadcast(new \App\Events\GameStarted($game, $current));
            $started = true;
        }

        return response()->json(['message' => 'Ready', 'started' => $started]);
    }

    public function shoot(Request $request): JsonResponse
    {
        $request->validate([
            'player_id' => 'required|integer|exists:players,id',
            'x' => 'required|integer|min:0|max:11',
            'y' => 'required|integer|min:0|max:11',
        ]);

        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $payload = DB::transaction(function () use ($request, $userId) {
            /** @var Player $player */
            $player = Player::lockForUpdate()->findOrFail($request->player_id);

            if ($player->user_id !== $userId) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            /** @var Player|null $enemy */
            $enemy = Player::where('game_id', $player->game_id)
                ->where('id', '<>', $player->id)
                ->lockForUpdate()
                ->first();

            if (!$enemy) {
                return response()->json(['error' => 'No enemy yet'], 400);
            }

            if (!$player->is_turn) {
                return response()->json(['error' => 'Not your turn'], 409);
            }

            $x = (int)$request->x;
            $y = (int)$request->y;

            return $this->battleService->resolveShot($player, $enemy, $x, $y);
        });

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        return response()->json($payload);
    }

    public function useAbility(Request $request): JsonResponse
    {
        $request->validate([
            'player_id' => 'required|integer|exists:players,id',
            'type' => 'required|string|in:plane,comb,splatter',
            'payload' => 'nullable|array',
        ]);

        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $payload = DB::transaction(function () use ($request, $userId) {
            /** @var Player $player */
            $player = Player::lockForUpdate()->findOrFail($request->player_id);

            if ($player->user_id !== $userId) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            /** @var Player|null $enemy */
            $enemy = Player::where('game_id', $player->game_id)
                ->where('id', '<>', $player->id)
                ->lockForUpdate()
                ->first();

            if (!$enemy) {
                return response()->json(['error' => 'No enemy yet'], 400);
            }
            if (!$player->is_turn) {
                return response()->json(['error' => 'Not your turn'], 409);
            }

            try {
                return $this->battleService->resolveAbility(
                    $player,
                    $enemy,
                    $request->string('type')->toString(),
                    $request->input('payload', [])
                );
            } catch (InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            } catch (\RuntimeException $e) {
                return response()->json(['error' => $e->getMessage()], 409);
            }
        });

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        return response()->json($payload);
    }

    public function rematch(Request $request): JsonResponse
    {
        $request->validate([
            'player_id' => 'required|integer|exists:players,id',
        ]);

        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $payload = DB::transaction(function () use ($request, $userId) {
            /** @var Player $player */
            $player = Player::lockForUpdate()->findOrFail($request->integer('player_id'));

            if ($player->user_id !== $userId) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            /** @var Game|null $game */
            $game = Game::lockForUpdate()->find($player->game_id);
            if (!$game) {
                return response()->json(['error' => 'Game not found'], 404);
            }

            if ($game->status !== Game::STATUS_COMPLETED) {
                return response()->json(['error' => 'Game still in progress'], 409);
            }

            if (!$player->wants_rematch) {
                $player->update(['wants_rematch' => true]);
            }

            /** @var Player|null $enemy */
            $enemy = Player::where('game_id', $player->game_id)
                ->where('id', '<>', $player->id)
                ->lockForUpdate()
                ->first();

            if (!$enemy) {
                return [
                    'status' => 'waiting',
                    'message' => 'Waiting for an opponent',
                ];
            }

            if (!$enemy->wants_rematch) {
                return [
                    'status' => 'waiting',
                    'message' => 'Waiting for opponent to accept rematch',
                ];
            }

            $players = [$player, $enemy];

            if ($game->winner_player_id) {
                usort($players, static function (Player $a, Player $b) use ($game): int {
                    if ($a->id === $game->winner_player_id && $b->id !== $game->winner_player_id) {
                        return -1;
                    }
                    if ($b->id === $game->winner_player_id && $a->id !== $game->winner_player_id) {
                        return 1;
                    }
                    return $a->id <=> $b->id;
                });
            } else {
                usort($players, static fn(Player $a, Player $b): int => $a->id <=> $b->id);
            }
            
            $newGame = Game::create();
            $newGame->update(['status' => Game::STATUS_CREATING]);

            $mapping = [];

            $newPlayers = [];
            foreach ($players as $index => $original) {
                $newPlayer = Player::create([
                    'user_id' => $original->user_id,
                    'game_id' => $newGame->id,
                    'name' => $original->name,
                    'is_turn' => $index === 0,
                    'is_ready' => false,
                ]);
                $newPlayers[] = $newPlayer;
                $mapping[] = [
                    'old_player_id' => $original->id,
                    'new_player_id' => $newPlayer->id,
                    'name' => $newPlayer->name,
                    'user_id' => $newPlayer->user_id,
                    'is_turn' => (bool)$newPlayer->is_turn,
                ];
            }

            if (!empty($newPlayers)) {
                $starter = $newPlayers[0];
                foreach ($newPlayers as $candidate) {
                    $candidate->forceFill([
                        'is_turn' => $candidate->id === $starter->id,
                        'turn_kills' => 0,
                        'ability_usage' => Player::defaultAbilityUsage(),
                    ])->save();
                }
                $mapping = array_map(static function (array $row) use ($starter) {
                    $row['is_turn'] = $row['new_player_id'] === $starter->id;
                    return $row;
                }, $mapping);
            }

            $player->update(['wants_rematch' => false]);
            $enemy->update(['wants_rematch' => false]);

            broadcast(new RematchReady($game, $newGame, $mapping));

            $current = collect($mapping)->firstWhere('old_player_id', $player->id);

            return [
                'status' => 'ready',
                'game' => [
                    'id' => $newGame->id,
                    'code' => $newGame->code,
                ],
                'player' => $current,
                'players' => $mapping,
            ];
        });

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        return response()->json($payload);
    }

    public function randomPlacement(Request $request): JsonResponse
    {
        $request->validate([
            'player_id' => 'nullable|integer|exists:players,id',
        ]);

        $userId = $request->user()?->id;

        $result = $this->placementService->randomFleet();

        if ($request->filled('player_id')) {
            $player = Player::find($request->integer('player_id'));
            if ($player) {
                if (!$userId || $player->user_id !== $userId) {
                    return response()->json(['error' => 'Forbidden'], 403);
                }

                $player->update([
                    'board' => $result['board'],
                    'ships' => $result['ships'],
                    'is_ready' => false,
                ]);
            }
        }

        return response()->json($result);
    }
    public function getAvailableGames(Request $request): JsonResponse
    {
        $games = Game::query()
            ->where('public', true)
            ->orderBy('created_at', 'desc')
            ->with('players')
            ->has('players', '=', 1)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->get()
            ->map(static function (Game $game) {
                return [
                    'id' => $game->id,
                    'code' => $game->code,
                    'enemy_name' => $game->players->first()?->name ?? 'Waiting for player',
                ];
            })
            ->values();

        return response()->json(['games' => $games]);
    }

    public function state(Request $request, Player $player): JsonResponse
    {
        $userId = $request->user()?->id;

        if (!$userId || $player->user_id !== $userId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($this->battleService->resolveState($player));
    }
    
    public function leaveGame(Request $request, Player $player): JsonResponse
    {
        $userId = $request->user()?->id;

        if (!$userId || $player->user_id !== $userId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $game = $player->game;

        if (!$game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        $gameId = $game->id;
        $gameCode = $game->code;
        $wasPublic = (bool)$game->public;
        $opponentCount = $game->players()
            ->where('players.id', '!=', $player->id)
            ->count();

        DB::transaction(static function () use ($gameId) {
            Player::query()
                ->where('game_id', $gameId)
                ->delete();

            Game::query()
                ->whereKey($gameId)
                ->delete();
        });

        if ($wasPublic) {
            broadcast(new PublicGameUnavailable($gameId));
        }

        if ($opponentCount > 0) {
            broadcast(new GameAborted($gameId, $gameCode));
        }

        return response()->json(['message' => 'Left the game']);
        
    }
}
