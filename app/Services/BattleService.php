<?php

namespace App\Services;

use App\Events\GameFinished;
use App\Events\ShipSunk;
use App\Events\ShotFired;
use App\Events\TurnChanged;
use App\Models\Game;
use App\Models\Move;
use App\Models\Player;
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
    ) {
    }

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
        if (!isset(self::ABILITY_LIMITS[$type])) {
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

        if (!$anyHit) {
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
        $enemy = $player->enemy();
        return [
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'board' => $player->board,
                'isTurn' => (bool)$player->is_turn,
                'isReady' => (bool)$player->is_ready,
                'abilityUsage' => $player->ability_usage,
                'turnKills' => $player->turn_kills,
            ],
            'enemy' => $enemy ? [
                'id' => $enemy->id,
                'name' => $enemy->name,
                'isTurn' => (bool)$enemy->is_turn,
                'isReady' => (bool)$enemy->is_ready,
            ] : null,
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
            $idx = max(0, min($this->boardSize - 1, (int)($payload['index'] ?? 0)));
            if ($axis === 'row') {
                return array_map(
                    fn(int $x) => [$x, $idx],
                    range(0, $this->boardSize - 1)
                );
            }
            return array_map(
                fn(int $y) => [$idx, $y],
                range(0, $this->boardSize - 1)
            );
        }

        if ($type === 'comb') {
            $center = Arr::get($payload, 'center');
            if (!is_array($center) || !isset($center['x'], $center['y'])) {
                throw new \InvalidArgumentException('Missing comb center');
            }

            $cx = (int)$center['x'];
            $cy = (int)$center['y'];
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
