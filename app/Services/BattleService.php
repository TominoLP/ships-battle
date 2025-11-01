<?php

namespace App\Services;

use App\Events\GameFinished;
use App\Events\ShipSunk;
use App\Events\ShotFired;
use App\Events\TurnChanged;
use App\Models\Game;
use App\Models\Move;
use App\Models\Player;
use App\Models\PlayerGameHistory;
use Illuminate\Support\Arr;

class BattleService
{
    private const ABILITY_LIMITS = [
        'plane' => 1,
        'splatter' => 2,
        'comb' => 1,
    ];

    public function __construct(
        private readonly PlacementService $placementService,
        private readonly int $boardSize = 12,
    ) {}

    public function resolveShot(Player $attacker, Player $defender, int $x, int $y): array
    {
        $board = $defender->board;
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
            $defender->update(['board' => $board]);

            $span = $this->placementService->collectShipSpan($board, $x, $y);
            if ($this->shipIsDestroyed($board, $span)) {
                $result = 'sunk';
                $sunkInfo = [
                    'size' => count($span),
                    'cells' => $span,
                ];
            }
        } elseif ($result === 'miss') {
            $board[$y][$x] = 3;
            $defender->update(['board' => $board]);
        }

        Move::create([
            'game_id' => $attacker->game_id,
            'player_id' => $attacker->id,
            'x' => $x,
            'y' => $y,
            'result' => $result,
        ]);

        broadcast(new ShotFired($attacker, $x, $y, $result));

        if ($sunkInfo) {
            broadcast(new ShipSunk($attacker, $sunkInfo['size'], $sunkInfo['cells']));
            $attacker->increment('turn_kills');
        }

        $gameOver = $this->isGameOver($board);

        if ($gameOver) {
            $this->finishGame($attacker, $defender);

            return [
                'shots' => [
                    ['x' => $x, 'y' => $y, 'result' => $result],
                ],
                'sunk' => $sunkInfo ? [$sunkInfo] : [],
                'gameOver' => true,
                'winner' => [
                    'id' => $attacker->id,
                    'name' => $attacker->name,
                ],
                'abilityUsage' => $attacker->ability_usage,
                'turnKills' => $attacker->turn_kills,
            ];
        }

        if ($result === 'miss') {
            $this->switchTurn($attacker, $defender);
        }

        return [
            'shots' => [
                ['x' => $x, 'y' => $y, 'result' => $result],
            ],
            'sunk' => $sunkInfo ? [$sunkInfo] : [],
            'gameOver' => false,
            'abilityUsage' => $attacker->ability_usage,
            'turnKills' => $attacker->turn_kills,
        ];
    }

    public function resolveAbility(Player $attacker, Player $defender, string $type, array $payload): array
    {
        $type = strtolower($type);
        if (! isset(self::ABILITY_LIMITS[$type])) {
            throw new \InvalidArgumentException('Unknown ability');
        }

        $usage = $attacker->ability_usage;
        if (($usage[$type] ?? 0) >= self::ABILITY_LIMITS[$type]) {
            throw new \RuntimeException('Ability exhausted');
        }
        if ($type === 'comb' && $attacker->turn_kills < 2) {
            throw new \RuntimeException('Bomb locked until two kills this turn');
        }

        $targets = $this->resolveTargets($type, $payload);
        $board = $defender->board;

        $shots = [];
        $sunkList = [];
        $anyHit = false;

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
                $defender->update(['board' => $board]);

                $span = $this->placementService->collectShipSpan($board, $x, $y);
                if ($this->shipIsDestroyed($board, $span)) {
                    $result = 'sunk';
                    $sunkInfo = [
                        'size' => count($span),
                        'cells' => $span,
                    ];
                    $sunkList[] = $sunkInfo;
                    $attacker->increment('turn_kills');
                }
            } elseif ($result === 'miss') {
                $board[$y][$x] = 3;
                $defender->update(['board' => $board]);
            }

            Move::create([
                'game_id' => $attacker->game_id,
                'player_id' => $attacker->id,
                'x' => $x,
                'y' => $y,
                'result' => $result,
            ]);

            broadcast(new ShotFired($attacker, $x, $y, $result));

            if ($sunkInfo) {
                broadcast(new ShipSunk($attacker, $sunkInfo['size'], $sunkInfo['cells']));
            }

            if ($result === 'hit' || $result === 'sunk') {
                $anyHit = true;
            }

            $shots[] = ['x' => $x, 'y' => $y, 'result' => $result];
        }

        $usage[$type] = ($usage[$type] ?? 0) + 1;
        $attacker->update(['ability_usage' => $usage]);

        $gameOver = $this->isGameOver($board);

        if ($gameOver) {
            $this->finishGame($attacker, $defender);

            return [
                'shots' => $shots,
                'sunk' => $sunkList,
                'gameOver' => true,
                'winner' => [
                    'id' => $attacker->id,
                    'name' => $attacker->name,
                ],
                'abilityUsage' => $attacker->ability_usage,
                'turnKills' => $attacker->turn_kills,
            ];
        }

        if (! $anyHit) {
            $this->switchTurn($attacker, $defender);
        }

        return [
            'shots' => $shots,
            'sunk' => $sunkList,
            'gameOver' => false,
            'abilityUsage' => $attacker->ability_usage,
            'turnKills' => $attacker->turn_kills,
        ];
    }

    public function resolveState(Player $player): array
    {
        $player->loadMissing('game');
        $game = $player->game;
        $enemy = $player->enemy();

        $playerShots = [];
        $enemyShots = [];

        if ($game) {
            $playerShots = Move::query()
                ->where('game_id', $game->id)
                ->where('player_id', $player->id)
                ->orderBy('id')
                ->get(['x', 'y', 'result'])
                ->map(static function (Move $move) {
                    return [
                        'x' => (int) $move->x,
                        'y' => (int) $move->y,
                        'result' => (string) $move->result,
                    ];
                })
                ->values()
                ->all();

            if ($enemy) {
                $enemyShots = Move::query()
                    ->where('game_id', $game->id)
                    ->where('player_id', $enemy->id)
                    ->orderBy('id')
                    ->get(['x', 'y', 'result'])
                    ->map(static function (Move $move) {
                        return [
                            'x' => (int) $move->x,
                            'y' => (int) $move->y,
                            'result' => (string) $move->result,
                        ];
                    })
                    ->values()
                    ->all();
            }
        }

        $winnerData = null;
        if ($game && $game->winner_player_id) {
            $winner = Player::find($game->winner_player_id);
            if ($winner) {
                $winnerData = [
                    'id' => $winner->id,
                    'name' => $winner->name,
                ];
            }
        }

        return [
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'board' => $player->board,
                'isTurn' => (bool) $player->is_turn,
                'isReady' => (bool) $player->is_ready,
                'abilityUsage' => $player->ability_usage,
                'turnKills' => $player->turn_kills,
            ],
            'enemy' => $enemy ? [
                'id' => $enemy->id,
                'name' => $enemy->name,
                'isTurn' => (bool) $enemy->is_turn,
                'isReady' => (bool) $enemy->is_ready,
            ] : null,
            'shots' => [
                'player' => $playerShots,
                'enemy' => $enemyShots,
            ],
            'game' => $game ? [
                'id' => $game->id,
                'code' => $game->code,
                'status' => $game->status,
                'winner_player_id' => $game->winner_player_id,
            ] : null,
            'winner' => $winnerData,
        ];
    }

    private function finishGame(Player $winner, Player $loser): void
    {
        $winner->update(['is_turn' => false]);
        $loser->update(['is_turn' => false]);

        /** @var Game $game */
        $game = $winner->game()->lockForUpdate()->first();
        $game->update([
            'status' => Game::STATUS_COMPLETED,
            'winner_player_id' => $winner->id,
        ]);

        broadcast(new GameFinished($game, $winner));

        $this->recordHistory($game, $winner, $loser);
    }

    private function recordHistory(Game $game, Player $winner, Player $loser): void
    {
        if ($game->is_bot_match) {
            return;
        }

        $gameId = $game->id;

        $winnerShipsDestroyed = Move::where('game_id', $gameId)
            ->where('player_id', $winner->id)
            ->where('result', 'sunk')
            ->count();
        $loserShipsDestroyed = Move::where('game_id', $gameId)
            ->where('player_id', $loser->id)
            ->where('result', 'sunk')
            ->count();

        $winnerShots = Move::where('game_id', $gameId)
            ->where('player_id', $winner->id)
            ->count();
        $loserShots = Move::where('game_id', $gameId)
            ->where('player_id', $loser->id)
            ->count();

        $winnerHits = Move::where('game_id', $gameId)
            ->where('player_id', $winner->id)
            ->whereIn('result', ['hit', 'sunk'])
            ->count();
        $loserHits = Move::where('game_id', $gameId)
            ->where('player_id', $loser->id)
            ->whereIn('result', ['hit', 'sunk'])
            ->count();

        $winnerShipsLost = $loserShipsDestroyed;
        $loserShipsLost = $winnerShipsDestroyed;

        $winnerUsage = $winner->ability_usage;
        $loserUsage = $loser->ability_usage;

        $winnerAbilities = is_array($winnerUsage)
            ? array_sum(array_map('intval', $winnerUsage))
            : 0;
        $loserAbilities = is_array($loserUsage)
            ? array_sum(array_map('intval', $loserUsage))
            : 0;

        if ($winner->user_id) {
            PlayerGameHistory::updateOrCreate(
                ['user_id' => $winner->user_id, 'game_id' => $gameId],
                [
                    'result' => 'win',
                    'ships_destroyed' => $winnerShipsDestroyed,
                    'ships_lost' => $winnerShipsLost,
                    'shots_fired' => $winnerShots,
                    'hits' => $winnerHits,
                    'abilities_used' => $winnerAbilities,
                ]
            );
        }

        if ($loser->user_id) {
            PlayerGameHistory::updateOrCreate(
                ['user_id' => $loser->user_id, 'game_id' => $gameId],
                [
                    'result' => 'loss',
                    'ships_destroyed' => $loserShipsDestroyed,
                    'ships_lost' => $loserShipsLost,
                    'shots_fired' => $loserShots,
                    'hits' => $loserHits,
                    'abilities_used' => $loserAbilities,
                ]
            );
        }
    }

    private function switchTurn(Player $current, Player $next): void
    {
        $current->update(['is_turn' => false, 'turn_kills' => 0]);
        $next->update(['is_turn' => true, 'turn_kills' => 0]);
        broadcast(new TurnChanged($current->game, $next));
    }

    private function shipIsDestroyed(array $board, array $span): bool
    {
        foreach ($span as [$x, $y]) {
            if (($board[$y][$x] ?? 0) === 1) {
                return false;
            }
        }

        return true;
    }

    private function isGameOver(array $board): bool
    {
        foreach ($board as $row) {
            foreach ($row as $cell) {
                if ($cell === 1) {
                    return false;
                }
            }
        }

        return true;
    }

    private function resolveTargets(string $type, array $payload): array
    {
        if ($type === 'plane') {
            $axis = ($payload['axis'] ?? 'row') === 'col' ? 'col' : 'row';
            $idx = max(0, min($this->boardSize - 1, (int) ($payload['index'] ?? 0)));
            if ($axis === 'row') {
                return array_map(
                    fn (int $x) => [$x, $idx],
                    range(0, $this->boardSize - 1)
                );
            }

            return array_map(
                fn (int $y) => [$idx, $y],
                range(0, $this->boardSize - 1)
            );
        }

        if ($type === 'comb') {
            $center = Arr::get($payload, 'center');
            if (! is_array($center) || ! isset($center['x'], $center['y'])) {
                throw new \InvalidArgumentException('Missing comb center');
            }

            $cx = (int) $center['x'];
            $cy = (int) $center['y'];
            $targets = [];
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    if (abs($dx) === 2 && abs($dy) === 2) {
                        continue;
                    }
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $this->boardSize && $y >= 0 && $y < $this->boardSize) {
                        $targets[] = [$x, $y];
                    }
                }
            }

            return $targets;
        }

        // splatter
        $total = $this->boardSize * $this->boardSize;
        $need = min(12, $total);
        $used = [];
        $targets = [];
        while (count($targets) < $need) {
            $n = random_int(0, $total - 1);
            if (isset($used[$n])) {
                continue;
            }
            $used[$n] = true;
            $targets[] = [$n % $this->boardSize, intdiv($n, $this->boardSize)];
        }

        return $targets;
    }
}
