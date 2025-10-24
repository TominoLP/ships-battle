<?php

namespace App\Http\Controllers;

use App\Events\GameCreated;
use App\Events\PlayerJoined;
use App\Events\PlayerReady;
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
            'name' => 'required|string|max:50',
        ]);

        $game = Game::where('code', $request->code)->firstOrFail();

        if ($game->players()->count() >= 2) {
            return response()->json(['error' => 'Game is full'], 400);
        }

        $player = Player::create([
            'game_id' => $game->id,
            'name' => $request->name,
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
        $request->validate(['name' => 'required|string|max:50']);

        $game = Game::create();
        $player = Player::create([
            'game_id' => $game->id,
            'name' => $request->name,
            'is_turn' => true,
        ]);

        broadcast(new GameCreated($game, $player));

        return response()->json([
            'game_code' => $game->code,
            'player_id' => $player->id,
            'game_id' => $game->id,
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

        $payload = DB::transaction(function () use ($request) {
            /** @var Player $player */
            $player = Player::lockForUpdate()->findOrFail($request->player_id);

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

        $payload = DB::transaction(function () use ($request) {
            /** @var Player $player */
            $player = Player::lockForUpdate()->findOrFail($request->player_id);

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

    public function randomPlacement(Request $request): JsonResponse
    {
        $request->validate([
            'player_id' => 'nullable|integer|exists:players,id',
        ]);

        $result = $this->placementService->randomFleet();

        if ($request->filled('player_id')) {
            $player = Player::find($request->integer('player_id'));
            if ($player) {
                $player->update([
                    'board' => $result['board'],
                    'ships' => $result['ships'],
                    'is_ready' => false,
                ]);
            }
        }

        return response()->json($result);
    }

    public function state(Player $player): JsonResponse
    {
        return response()->json($this->battleService->resolveState($player));
    }
}
