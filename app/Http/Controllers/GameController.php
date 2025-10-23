<?php

namespace App\Http\Controllers;

use App\Events\PlayerJoined;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
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

        // switch to "creating" (ship placement)
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

        broadcast(new \App\Events\GameCreated($game, $player));

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
            'x' => (int)$s['x'], 'y' => (int)$s['y'],
            'size' => (int)$s['size'], 'dir' => $s['dir']
        ]);

        // 1) Fleet must match exactly: 1×5, 2×4, 3×3, 4×2
        $expected = [5 => 1, 4 => 2, 3 => 3, 2 => 4];
        $counts = $ships->groupBy('size')->map->count()->all();
        foreach ($expected as $size => $need) {
            if (($counts[$size] ?? 0) !== $need) {
                return response()->json(['error' => "Invalid fleet: need {$need} of size {$size}"], 422);
            }
        }
        if ($ships->count() !== array_sum($expected)) {
            return response()->json(['error' => 'Invalid fleet count'], 422);
        }

        $board = array_fill(0, 12, array_fill(0, 12, 0));

        $inBounds = fn($x, $y) => $x >= 0 && $x < 12 && $y >= 0 && $y < 12;
        $canPlace = function ($x, $y, $size, $dir) use (&$board, $inBounds) {
            // no-touch rule (8-neighbours)
            for ($i = 0; $i < $size; $i++) {
                $cx = $dir === 'H' ? $x + $i : $x;
                $cy = $dir === 'V' ? $y + $i : $y;
                if (!$inBounds($cx, $cy)) return false;
                if ($board[$cy][$cx] !== 0) return false;
                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $nx = $cx + $dx;
                        $ny = $cy + $dy;
                        if ($inBounds($nx, $ny) && $board[$ny][$nx] === 1) return false;
                    }
                }
            }
            return true;
        };

        foreach ($ships as $s) {
            if (!$canPlace($s['x'], $s['y'], $s['size'], $s['dir'])) {
                return response()->json(['error' => 'Ship placement invalid (bounds/overlap/touch)'], 422);
            }
            for ($i = 0; $i < $s['size']; $i++) {
                $cx = $s['dir'] === 'H' ? $s['x'] + $i : $s['x'];
                $cy = $s['dir'] === 'V' ? $s['y'] + $i : $s['y'];
                $board[$cy][$cx] = 1;
            }
        }

        // 3) Persist
        $player->update([
            'board' => $board,
            'is_ready' => true,
        ]);

        broadcast(new \App\Events\PlayerReady($player));

        // 4) Start game if both ready (and set/keep a starter)
        $game = $player->game;
        $started = false;

        if ($game->players()->count() === 2 && $game->players()->where('is_ready', true)->count() === 2) {
            // ensure exactly one starter
            $players = $game->players()->orderBy('id')->get();
            if ($players->where('is_turn', true)->count() !== 1) {
                $game->players()->update(['is_turn' => false]);
                $starter = $players->first(); // or ->inRandomOrder()->first()
                $starter->update(['is_turn' => true]);
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

        // small helpers (pure array ops)
        $isShipCell = static function (array $board, int $x, int $y): bool {
            return isset($board[$y][$x]) && ($board[$y][$x] === 1 || $board[$y][$x] === 2);
        };
        $collectShipSpan = static function (array $board, int $x, int $y) use ($isShipCell): array {
            // Determine axis by looking at neighbors
            $horiz = $isShipCell($board, $x - 1, $y) || $isShipCell($board, $x + 1, $y);
            $vert = $isShipCell($board, $x, $y - 1) || $isShipCell($board, $x, $y + 1);

            $cells = [[$x, $y]];

            if ($horiz) {
                // left
                $cx = $x - 1;
                while ($isShipCell($board, $cx, $y)) {
                    $cells[] = [$cx, $y];
                    $cx--;
                }
                // right
                $cx = $x + 1;
                while ($isShipCell($board, $cx, $y)) {
                    $cells[] = [$cx, $y];
                    $cx++;
                }
            } elseif ($vert) {
                // up
                $cy = $y - 1;
                while ($isShipCell($board, $x, $cy)) {
                    $cells[] = [$x, $cy];
                    $cy--;
                }
                // down
                $cy = $y + 1;
                while ($isShipCell($board, $x, $cy)) {
                    $cells[] = [$x, $cy];
                    $cy++;
                }
            }
            // single-cell ships would just be the cell itself
            return $cells;
        };

        $payload = DB::transaction(function () use ($request, $isShipCell, $collectShipSpan) {
            /** @var Player $player */
            $player = \App\Models\Player::lockForUpdate()->findOrFail($request->player_id);

            /** @var Player|null $enemy */
            $enemy = \App\Models\Player::where('game_id', $player->game_id)
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

            $board = $enemy->board; // array (cast on model)
            $cell = $board[$y][$x] ?? 0;

            // 0 empty, 1 ship, 2 hit, 3 miss
            $result = match ($cell) {
                0 => 'miss',
                1 => 'hit',
                2, 3 => 'already',
                default => 'miss',
            };

            $sunkInfo = null;

            if ($result === 'hit') {
                // mark the hit
                $board[$y][$x] = 2;
                $enemy->update(['board' => $board]);

                // check if the entire ship is now sunk
                $spanCells = $collectShipSpan($board, $x, $y);
                $anyUndamaged = false;
                foreach ($spanCells as [$cx, $cy]) {
                    if (($board[$cy][$cx] ?? 0) === 1) {
                        $anyUndamaged = true;
                        break;
                    }
                }
                if (!$anyUndamaged) {
                    // the ship spanning through (x,y) is fully destroyed
                    $result = 'sunk';
                    $sunkInfo = [
                        'size' => count($spanCells),
                        'cells' => $spanCells, // [[x,y], ...]
                    ];
                }
            } elseif ($result === 'miss') {
                $board[$y][$x] = 3;
                $enemy->update(['board' => $board]);
            }

            // record move
            \App\Models\Move::create([
                'game_id' => $player->game_id,
                'player_id' => $player->id,
                'x' => $x,
                'y' => $y,
                'result' => $result, // now can be 'sunk'
            ]);

            // broadcast shot first
            broadcast(new \App\Events\ShotFired($player, $x, $y, $result));

            // if we sunk something, emit ShipSunk now
            if ($sunkInfo) {
                // you can change the signature to whatever your event expects
                broadcast(new \App\Events\ShipSunk($player, $sunkInfo['size'], $sunkInfo['cells']));
            }

            // check for game over using the updated $board
            $gameOver = !collect($board)->flatten()->contains(1);

            if ($gameOver) {
                $player->update(['is_turn' => false]);
                $enemy->update(['is_turn' => false]);

                $game = $player->game()->lockForUpdate()->first();
                $game->update([
                    'status' => \App\Models\Game::STATUS_COMPLETED,
                    'winner_player_id' => $player->id,
                ]);

                broadcast(new \App\Events\GameFinished($game, $player));

                return response()->json([
                    'result' => $result,
                    'gameOver' => true,
                    'winner' => ['id' => $player->id, 'name' => $player->name],
                ]);
            }

            // turn handling
            if ($result === 'miss') {
                $player->update(['is_turn' => false]);
                $enemy->update(['is_turn' => true]);
                broadcast(new \App\Events\TurnChanged($player->game, $enemy));
            }

            return [
                'result' => $result,   // 'hit' | 'miss' | 'already' | 'sunk'
                'gameOver' => false,
            ];
        });

        if ($payload instanceof \Illuminate\Http\JsonResponse) {
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
            $player = \App\Models\Player::lockForUpdate()->findOrFail($request->player_id);

            /** @var Player|null $enemy */
            $enemy = \App\Models\Player::where('game_id', $player->game_id)
                ->where('id', '<>', $player->id)
                ->lockForUpdate()
                ->first();

            if (!$enemy) {
                return response()->json(['error' => 'No enemy yet'], 400);
            }
            if (!$player->is_turn) {
                return response()->json(['error' => 'Not your turn'], 409);
            }

            $type = $request->string('type')->toString();
            $pl = $request->input('payload', []);

            // board size (12×12)
            $size = 12;

            // ---- helper closures (mirror those in shoot) ----
            $isShipCell = static function (array $board, int $x, int $y): bool {
                return isset($board[$y][$x]) && ($board[$y][$x] === 1 || $board[$y][$x] === 2);
            };
            $collectShipSpan = static function (array $board, int $x, int $y) use ($isShipCell): array {
                $horiz = $isShipCell($board, $x - 1, $y) || $isShipCell($board, $x + 1, $y);
                $vert  = $isShipCell($board, $x, $y - 1) || $isShipCell($board, $x, $y + 1);
                $cells = [[$x, $y]];
                if ($horiz) {
                    for ($cx = $x - 1; $isShipCell($board, $cx, $y); $cx--) $cells[] = [$cx, $y];
                    for ($cx = $x + 1; $isShipCell($board, $cx, $y); $cx++) $cells[] = [$cx, $y];
                } elseif ($vert) {
                    for ($cy = $y - 1; $isShipCell($board, $x, $cy); $cy--) $cells[] = [$x, $cy];
                    for ($cy = $y + 1; $isShipCell($board, $x, $cy); $cy++) $cells[] = [$x, $cy];
                }
                return $cells;
            };

            // compute target cells
            $targets = [];
            if ($type === 'plane') {
                // payload: { axis: 'row'|'col', index: 0..11 }
                $axis = ($pl['axis'] ?? 'row') === 'col' ? 'col' : 'row';
                $idx  = max(0, min($size - 1, (int)($pl['index'] ?? 0)));
                if ($axis === 'row') {
                    for ($x = 0; $x < $size; $x++) $targets[] = [$x, $idx];
                } else {
                    for ($y = 0; $y < $size; $y++) $targets[] = [$idx, $y];
                }
            } elseif ($type === 'comb') {
                // payload: { center: { x, y } }
                $c = $pl['center'] ?? null;
                if (!is_array($c) || !isset($c['x'], $c['y'])) {
                    return response()->json(['error' => 'Missing comb center'], 422);
                }
                $cx = (int)$c['x']; $cy = (int)$c['y'];
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        // skip 4 corners
                        if (abs($dx) === 2 && abs($dy) === 2) continue;
                        $x = $cx + $dx; $y = $cy + $dy;
                        if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) $targets[] = [$x, $y];
                    }
                }
            } else { // splatter
                // 12 random unique cells in-bounds
                $total = $size * $size;
                $need  = min(12, $total);
                $picked = [];
                $used = [];
                while (count($picked) < $need) {
                    $n = random_int(0, $total - 1);
                    if (isset($used[$n])) continue;
                    $used[$n] = true;
                    $picked[] = [ $n % $size, intdiv($n, $size) ];
                }
                $targets = $picked;
            }

            // process each target (like shoot), mutating enemy board
            $board = $enemy->board; // 0 empty, 1 ship, 2 hit, 3 miss
            $shots = [];
            $anyHit = false;
            $sunkList = [];

            foreach ($targets as [$x, $y]) {
                $cell = $board[$y][$x] ?? 0;
                $result = match ($cell) {
                    0 => 'miss',
                    1 => 'hit',
                    2, 3 => 'already',
                    default => 'miss',
                };

                $sunkInfo = null;

                if ($result === 'hit') {
                    $board[$y][$x] = 2;
                    $enemy->update(['board' => $board]);

                    $spanCells = $collectShipSpan($board, $x, $y);
                    $anyUndamaged = false;
                    foreach ($spanCells as [$cx, $cy]) {
                        if (($board[$cy][$cx] ?? 0) === 1) { $anyUndamaged = true; break; }
                    }
                    if (!$anyUndamaged) {
                        $result = 'sunk';
                        $sunkInfo = ['size' => count($spanCells), 'cells' => $spanCells];
                        $sunkList[] = $sunkInfo;
                    }
                } elseif ($result === 'miss') {
                    $board[$y][$x] = 3;
                    $enemy->update(['board' => $board]);
                }

                // record each move & broadcast like normal shots
                \App\Models\Move::create([
                    'game_id' => $player->game_id,
                    'player_id' => $player->id,
                    'x' => $x, 'y' => $y,
                    'result' => $result,
                ]);

                broadcast(new \App\Events\ShotFired($player, $x, $y, $result));

                if ($sunkInfo) {
                    broadcast(new \App\Events\ShipSunk($player, $sunkInfo['size'], $sunkInfo['cells']));
                }

                if ($result === 'hit' || $result === 'sunk') $anyHit = true;

                $shots[] = ['x' => $x, 'y' => $y, 'result' => $result];
            }

            // game over?
            $gameOver = !collect($board)->flatten()->contains(1);
            if ($gameOver) {
                $player->update(['is_turn' => false]);
                $enemy->update(['is_turn' => false]);

                $game = $player->game()->lockForUpdate()->first();
                $game->update([
                    'status' => \App\Models\Game::STATUS_COMPLETED,
                    'winner_player_id' => $player->id,
                ]);

                broadcast(new \App\Events\GameFinished($game, $player));

                return response()->json([
                    'shots' => $shots,
                    'sunk' => $sunkList,
                    'gameOver' => true,
                    'winner' => ['id' => $player->id, 'name' => $player->name],
                ]);
            }

            // turn handling: keep turn if any hit, otherwise switch
            if (!$anyHit) {
                $player->update(['is_turn' => false]);
                $enemy->update(['is_turn' => true]);
                broadcast(new \App\Events\TurnChanged($player->game, $enemy));
            }

            return [
                'shots' => $shots,
                'sunk' => $sunkList,
                'gameOver' => false,
            ];
        });

        if ($payload instanceof \Illuminate\Http\JsonResponse) return $payload;

        return response()->json($payload);
    }


}
