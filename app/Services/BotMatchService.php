<?php

namespace App\Services;

use App\Events\GameCreated;
use App\Events\PlayerJoined;
use App\Models\Game;
use App\Models\Move;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BotMatchService
{
    private const BOT_NAME = '';
    private const BOARD_SIZE = 12;

    public function __construct(
        private readonly PlacementService $placementService,
        private readonly BattleService $battleService,
    ) {}

    /**
     * Set up a fresh game against the bot for the given user.
     *
     * @return array{game: Game, player: Player, bot: Player}
     */
    public function createFor(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $game = Game::create([
                'public' => false,
                'is_bot_match' => true,
            ]);

            $player = Player::create([
                'user_id' => $user->id,
                'game_id' => $game->id,
                'name' => $this->resolvePlayerName($user),
                'is_turn' => true,
                'is_ready' => false,
            ]);

            $botFleet = $this->placementService->randomFleet();
            $bot = Player::create([
                'user_id' => null,
                'game_id' => $game->id,
                'name' => self::genBotName(),
                'is_turn' => false,
                'is_ready' => true,
                'board' => $botFleet['board'],
                'ships' => $botFleet['ships'],
            ]);

            $game->update(['status' => Game::STATUS_CREATING]);

            broadcast(new GameCreated($game, $player));
            broadcast(new PlayerJoined($game, $bot));

            return [
                'game' => $game->fresh(),
                'player' => $player->fresh(),
                'bot' => $bot->fresh(),
            ];
        });
    }
    
    private static function genBotName(): string
    {
        $nouns = ['Eagle', 'Tiger', 'Shark', 'Wolf', 'Falcon', 'Panther', 'Dragon', 'Hawk'];
        $noun = $nouns[array_rand($nouns)];
        return 'Trainer ' . $noun;
    }

    /**
     * If the referenced player's game is a bot match, let the bot take turns until it finishes or misses.
     *
     * @return array{shots: array<int, array<string, mixed>>, sunk: array<int, array<string, mixed>>, gameOver: bool, winner: array<string, mixed>|null}|null
     */
    public function syncBotTurn(Player $reference): ?array
    {
        $reference->loadMissing('game');
        $game = $reference->game;

        if (! $game || ! $game->is_bot_match || $game->status !== Game::STATUS_IN_PROGRESS) {
            return null;
        }

        $game->loadMissing('players');
        $bot = $game->players->firstWhere(fn (Player $player) => $player->user_id === null);
       $opponent = $game->players->firstWhere(fn (Player $player) => $player->user_id !== null);

        if (! $bot || ! $opponent || ! $bot->is_turn) {
            return null;
        }

        $shots = [];
        $sunk = [];
        $actions = [];
        $gameOver = false;
        $winner = null;

        for ($i = 0; $i < 12 && $bot->is_turn && $game->status === Game::STATUS_IN_PROGRESS; $i++) {
            $state = $this->buildState($bot);
            $decision = $this->decideAction($bot, $opponent, $state);

            if (! $decision) {
                break;
            }

            if ($decision['type'] === 'ability') {
                $result = $this->battleService->resolveAbility(
                    $bot,
                    $opponent,
                    $decision['ability'],
                    $decision['payload']
                );
                $result['ability'] = $decision['ability'];
            } else {
                [$x, $y] = $decision['target'];
                $result = $this->battleService->resolveShot($bot, $opponent, $x, $y);
                $result['ability'] = null;
            }

            $segmentShots = Arr::get($result, 'shots', []);
            $shots = array_merge($shots, $segmentShots);
            $sunk = array_merge($sunk, Arr::get($result, 'sunk', []));

            $actions[] = [
                'type' => $result['ability'] ? 'ability' : 'shot',
                'ability' => $result['ability'],
                'shots' => $segmentShots,
                'sunk' => Arr::get($result, 'sunk', []),
            ];

            if (! empty($result['gameOver'])) {
                $gameOver = true;
                $winner = $result['winner'] ?? null;
                break;
            }

            $bot->refresh();
            $opponent->refresh();
            $game->refresh();
        }

        if (empty($shots) && ! $gameOver) {
            return null;
        }

        return [
            'shots' => $shots,
            'sunk' => $sunk,
            'actions' => $actions,
            'gameOver' => $gameOver,
            'winner' => $winner,
        ];
    }

    private function resolvePlayerName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : 'Player '.$user->id;
    }

    /**
     * Pick the next coordinate the bot should fire at.
     *
     * @return array{0:int,1:int}|null
     */
    /**
     * Build current knowledge from recorded moves.
     *
     * @return array{
     *     known: array<int, array<int,int>>,
     *     sunkSegments: array<int, array<int,array{int,int}>>,
     *     blocked: array<int, array<int,bool>>,
     *     openSegments: array<int, array{cells: array<int,array{int,int}>, orientation: 'H'|'V'|null, min: array{int,int}, max: array{int,int}}>,
     *     remainingShips: int[],
     *     turnCount: int
     * }
     */
    private function buildState(Player $bot): array
    {
        $size = self::BOARD_SIZE;
        $known = array_fill(0, $size, array_fill(0, $size, 0));
        $sunkMask = array_fill(0, $size, array_fill(0, $size, false));
        $sunkSegments = [];

        $moves = Move::query()
            ->where('game_id', $bot->game_id)
            ->where('player_id', $bot->id)
            ->orderBy('id')
            ->get(['x', 'y', 'result']);

        foreach ($moves as $move) {
            $x = (int) $move->x;
            $y = (int) $move->y;
            if ($x < 0 || $x >= $size || $y < 0 || $y >= $size) {
                continue;
            }

            $result = strtolower((string) $move->result);
            if ($result === 'miss' || $result === 'already') {
                $known[$y][$x] = 1;
                continue;
            }

            if (! in_array($result, ['hit', 'sunk'], true)) {
                continue;
            }

            $known[$y][$x] = 2;

            if ($result === 'sunk') {
                $component = $this->collectComponent($known, $sunkMask, $x, $y);
                if (! empty($component)) {
                    $sunkSegments[] = $component;
                    foreach ($component as [$cx, $cy]) {
                        $sunkMask[$cy][$cx] = true;
                    }
                }
            }
        }

        $blocked = $this->buildBlocked($sunkSegments, $size);
        $openSegments = $this->collectOpenSegments($known, $sunkMask);

        $fleet = [5, 4, 4, 3, 3, 3, 2, 2, 2, 2];
        foreach ($sunkSegments as $segment) {
            $length = count($segment);
            $index = array_search($length, $fleet, true);
            if ($index !== false) {
                unset($fleet[$index]);
                $fleet = array_values($fleet);
            }
        }

        return [
            'known' => $known,
            'blocked' => $blocked,
            'openSegments' => $openSegments,
            'remainingShips' => $fleet,
            'turnCount' => $moves->count(),
        ];
    }

    private function decideAction(Player $bot, Player $opponent, array $state): ?array
    {
        $abilityDecision = $this->decideAbility($bot, $state);
        if ($abilityDecision) {
            return $abilityDecision;
        }

        $target = $this->targetMode($state);

        if (! $target) {
            $target = $this->huntMode($state);
        }

        if (! $target) {
            return null;
        }

        return [
            'type' => 'shot',
            'target' => $target,
        ];
    }

    private function decideAbility(Player $bot, array $state): ?array
    {
        $usage = $bot->ability_usage ?? Player::defaultAbilityUsage();

        // Plane: first move, deterministic sweep along highest heat axis.
        if (($usage['plane'] ?? 0) === 0 && $state['turnCount'] === 0) {
            $heat = $this->buildHeatMap($state);
            [$axis, $index] = $this->choosePlaneAxis($heat);

            return [
                'type' => 'ability',
                'ability' => 'plane',
                'payload' => ['axis' => $axis, 'index' => $index],
            ];
        }

        // Comb: orientation known + available charges + bot has turn kills prerequisite.
        if (($usage['comb'] ?? 0) === 0 && $bot->turn_kills >= 2) {
            foreach ($state['openSegments'] as $segment) {
                if ($segment['orientation'] === null) {
                    continue;
                }

                $center = $this->chooseCombCenterFromSegment($segment);
                if ($center) {
                    return [
                        'type' => 'ability',
                        'ability' => 'comb',
                        'payload' => ['center' => $center],
                    ];
                }
            }
        }

        // Splatter: only in hunt mode when heat map is very flat.
        if (($usage['splatter'] ?? 0) < 2 && empty($state['openSegments'])) {
            $heat = $this->buildHeatMap($state);
            $maxHeat = 0;
            foreach ($heat as $row) {
                foreach ($row as $value) {
                    $maxHeat = max($maxHeat, $value);
                }
            }

            if ($maxHeat <= 2 && $state['turnCount'] >= 6) {
                return [
                    'type' => 'ability',
                    'ability' => 'splatter',
                    'payload' => [],
                ];
            }
        }

        return null;
    }

    private function targetMode(array $state): ?array
    {
        foreach ($state['openSegments'] as $segment) {
            $target = $this->nextTargetForSegment($segment, $state['known'], $state['blocked']);
            if ($target) {
                return $target;
            }
        }

        return null;
    }

    private function huntMode(array $state): ?array
    {
        $heat = $this->buildHeatMap($state);
        $max = 0;
        $candidates = [];

        for ($y = 0; $y < self::BOARD_SIZE; $y++) {
            for ($x = 0; $x < self::BOARD_SIZE; $x++) {
                $score = $heat[$y][$x];
                if ($score === 0) {
                    continue;
                }

                $known = $state['known'][$y][$x] ?? 0;
                if ($known !== 0) {
                    continue;
                }

                if ($score > $max) {
                    $max = $score;
                    $candidates = [[$x, $y]];
                } elseif ($score === $max) {
                    $candidates[] = [$x, $y];
                }
            }
        }

        if ($max === 0 || empty($candidates)) {
            return null;
        }

        $even = array_filter($candidates, static fn ($point) => (($point[0] + $point[1]) % 2) === 0);
        $pool = ! empty($even) ? $even : $candidates;

        return $pool[array_rand($pool)];
    }

    private function buildBlocked(array $sunkSegments, int $size): array
    {
        $blocked = array_fill(0, $size, array_fill(0, $size, false));

        foreach ($sunkSegments as $segment) {
            foreach ($segment as [$x, $y]) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $nx = $x + $dx;
                        $ny = $y + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $size || $ny >= $size) {
                            continue;
                        }
                        $blocked[$ny][$nx] = true;
                    }
                }
            }
        }

        return $blocked;
    }

    private function collectComponent(array $known, array $sunkMask, int $x, int $y): array
    {
        $size = count($known);
        $stack = [[$x, $y]];
        $component = [];
        $visited = array_fill(0, $size, array_fill(0, $size, false));

        while ($stack) {
            [$cx, $cy] = array_pop($stack);
            if ($cx < 0 || $cy < 0 || $cx >= $size || $cy >= $size) {
                continue;
            }
            if ($visited[$cy][$cx]) {
                continue;
            }
            $visited[$cy][$cx] = true;

            if ($known[$cy][$cx] !== 2) {
                continue;
            }

            if ($sunkMask[$cy][$cx]) {
                continue;
            }

            $component[] = [$cx, $cy];

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $stack[] = [$cx + $dx, $cy + $dy];
            }
        }

        return $component;
    }

    private function collectOpenSegments(array $known, array $sunkMask): array
    {
        $size = count($known);
        $visited = array_fill(0, $size, array_fill(0, $size, false));
        $segments = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($visited[$y][$x] || $known[$y][$x] !== 2 || $sunkMask[$y][$x]) {
                    continue;
                }

                $stack = [[$x, $y]];
                $cells = [];

                while ($stack) {
                    [$cx, $cy] = array_pop($stack);
                    if ($cx < 0 || $cy < 0 || $cx >= $size || $cy >= $size) {
                        continue;
                    }
                    if ($visited[$cy][$cx]) {
                        continue;
                    }
                    $visited[$cy][$cx] = true;

                    if ($known[$cy][$cx] !== 2 || $sunkMask[$cy][$cx]) {
                        continue;
                    }

                    $cells[] = [$cx, $cy];

                    foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                        $stack[] = [$cx + $dx, $cy + $dy];
                    }
                }

                if (empty($cells)) {
                    continue;
                }

                $xs = array_column($cells, 0);
                $ys = array_column($cells, 1);

                $orientation = null;
                if (count(array_unique($ys)) === 1 && count($cells) > 1) {
                    $orientation = 'H';
                } elseif (count(array_unique($xs)) === 1 && count($cells) > 1) {
                    $orientation = 'V';
                }

                $segments[] = [
                    'cells' => $cells,
                    'orientation' => $orientation,
                    'min' => [min($xs), min($ys)],
                    'max' => [max($xs), max($ys)],
                ];
            }
        }

        usort($segments, static function ($a, $b) {
            return count($b['cells']) <=> count($a['cells']);
        });

        return $segments;
    }

    private function nextTargetForSegment(array $segment, array $known, array $blocked): ?array
    {
        $cells = $segment['cells'];
        if (empty($cells)) {
            return null;
        }

        if ($segment['orientation'] === 'H') {
            $y = $cells[0][1];
            $xs = array_column($cells, 0);
            sort($xs);
            $left = $xs[0] - 1;
            $right = end($xs) + 1;

            if ($left >= 0 && $known[$y][$left] === 0 && ! $blocked[$y][$left]) {
                return [$left, $y];
            }
            if ($right < self::BOARD_SIZE && $known[$y][$right] === 0 && ! $blocked[$y][$right]) {
                return [$right, $y];
            }
        } elseif ($segment['orientation'] === 'V') {
            $x = $cells[0][0];
            $ys = array_column($cells, 1);
            sort($ys);
            $top = $ys[0] - 1;
            $bottom = end($ys) + 1;

            if ($top >= 0 && $known[$top][$x] === 0 && ! $blocked[$top][$x]) {
                return [$x, $top];
            }
            if ($bottom < self::BOARD_SIZE && $known[$bottom][$x] === 0 && ! $blocked[$bottom][$x]) {
                return [$x, $bottom];
            }
        } else {
            $x = $cells[0][0];
            $y = $cells[0][1];

            foreach ([[0, -1], [1, 0], [0, 1], [-1, 0]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                if ($nx < 0 || $ny < 0 || $nx >= self::BOARD_SIZE || $ny >= self::BOARD_SIZE) {
                    continue;
                }
                if ($known[$ny][$nx] === 0 && ! $blocked[$ny][$nx]) {
                    return [$nx, $ny];
                }
            }
        }

        return null;
    }

    private function buildHeatMap(array $state): array
    {
        $size = self::BOARD_SIZE;
        $heat = array_fill(0, $size, array_fill(0, $size, 0));
        $known = $state['known'];
        $blocked = $state['blocked'];
        foreach ($state['remainingShips'] as $length) {
            if ($length <= 0) {
                continue;
            }
            // Horizontal
            for ($y = 0; $y < $size; $y++) {
                for ($x = 0; $x <= $size - $length; $x++) {
                    if (! $this->placementFits($known, $blocked, $state['openSegments'], $x, $y, $length, 'H')) {
                        continue;
                    }
                    for ($i = 0; $i < $length; $i++) {
                        if ($known[$y][$x + $i] === 0) {
                            $heat[$y][$x + $i]++;
                        }
                    }
                }
            }

            // Vertical
            for ($x = 0; $x < $size; $x++) {
                for ($y = 0; $y <= $size - $length; $y++) {
                    if (! $this->placementFits($known, $blocked, $state['openSegments'], $x, $y, $length, 'V')) {
                        continue;
                    }
                    for ($i = 0; $i < $length; $i++) {
                        if ($known[$y + $i][$x] === 0) {
                            $heat[$y + $i][$x]++;
                        }
                    }
                }
            }
        }

        return $heat;
    }

    private function placementFits(array $known, array $blocked, array $segments, int $x, int $y, int $length, string $orientation): bool
    {
        $cells = [];

        for ($i = 0; $i < $length; $i++) {
            $cx = $orientation === 'H' ? $x + $i : $x;
            $cy = $orientation === 'V' ? $y + $i : $y;

            if ($cx < 0 || $cy < 0 || $cx >= self::BOARD_SIZE || $cy >= self::BOARD_SIZE) {
                return false;
            }

            if ($blocked[$cy][$cx]) {
                return false;
            }

            $cell = $known[$cy][$cx];
            if ($cell === 1) {
                return false;
            }

            $cells["{$cx}-{$cy}"] = $cell;
        }

        foreach ($segments as $segment) {
            $segmentCells = $segment['cells'];

            if ($segment['orientation'] === 'H') {
                $row = $segmentCells[0][1];
                $minX = min(array_column($segmentCells, 0));
                $maxX = max(array_column($segmentCells, 0));

                if ($orientation === 'H' && $y === $row) {
                    if ($x > $minX || ($x + $length - 1) < $maxX) {
                        return false;
                    }
                    continue;
                }

                foreach ($segmentCells as [$sx, $sy]) {
                    if (isset($cells["{$sx}-{$sy}"])) {
                        return false;
                    }
                }

                continue;
            }

            if ($segment['orientation'] === 'V') {
                $col = $segmentCells[0][0];
                $minY = min(array_column($segmentCells, 1));
                $maxY = max(array_column($segmentCells, 1));

                if ($orientation === 'V' && $x === $col) {
                    if ($y > $minY || ($y + $length - 1) < $maxY) {
                        return false;
                    }
                    continue;
                }

                foreach ($segmentCells as [$sx, $sy]) {
                    if (isset($cells["{$sx}-{$sy}"])) {
                        return false;
                    }
                }

                continue;
            }

            [$hx, $hy] = $segmentCells[0];
            if (! isset($cells["{$hx}-{$hy}"])) {
                return false;
            }
        }

        return true;
    }

    private function choosePlaneAxis(array $heat): array
    {
        $size = count($heat);
        $rowScores = array_fill(0, $size, 0);
        $colScores = array_fill(0, $size, 0);

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rowScores[$y] += $heat[$y][$x];
                $colScores[$x] += $heat[$y][$x];
            }
        }

        $bestRow = $this->argMaxWithFallback($rowScores);
        $bestCol = $this->argMaxWithFallback($colScores);

        if ($rowScores[$bestRow] >= $colScores[$bestCol]) {
            return ['row', $bestRow];
        }

        return ['col', $bestCol];
    }

    private function chooseCombCenterFromSegment(array $segment): ?array
    {
        if (empty($segment['cells'])) {
            return null;
        }

        $xs = array_column($segment['cells'], 0);
        $ys = array_column($segment['cells'], 1);

        $x = (int) round(array_sum($xs) / count($xs));
        $y = (int) round(array_sum($ys) / count($ys));

        $x = max(2, min(self::BOARD_SIZE - 3, $x));
        $y = max(2, min(self::BOARD_SIZE - 3, $y));

        return ['x' => $x, 'y' => $y];
    }

    private function argMaxWithFallback(array $values): int
    {
        $bestIndex = 0;
        $bestValue = $values[0] ?? 0;

        foreach ($values as $index => $value) {
            if ($value > $bestValue) {
                $bestValue = $value;
                $bestIndex = (int) $index;
            }
        }

        return (int) $bestIndex;
    }
}
