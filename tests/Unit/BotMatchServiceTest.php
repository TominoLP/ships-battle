<?php

namespace Tests\Unit;

use App\Jobs\BotTurnJob;
use App\Models\Move;
use App\Models\Game;
use App\Models\User;
use App\Services\BattleService;
use App\Services\BotMatchService;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BotMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_executes_turn_after_player_miss(): void
    {
        $user = User::factory()->create();

        $botMatches = app(BotMatchService::class);
        $placement = app(PlacementService::class);
        $battle = app(BattleService::class);

        $match = $botMatches->createFor($user);
        $player = $match['player'];
        $bot = $match['bot'];
        $game = $match['game'];

        $playerFleet = $placement->randomFleet();
        $player->update([
            'board' => $playerFleet['board'],
            'ships' => $playerFleet['ships'],
            'is_ready' => true,
        ]);

        $game->refresh();
        $game->update(['status' => Game::STATUS_IN_PROGRESS]);

        $player->refresh();
        $bot->refresh();

        [$missX, $missY] = $this->findMissCoordinate($bot->board);

        $battle->resolveShot($player, $bot, $missX, $missY);

        $player->refresh();
        $bot->refresh();

        $result = $botMatches->syncBotTurn($player);

        $this->assertNotNull($result, 'Bot should respond with a turn payload.');
        $this->assertNotEmpty($result['shots'] ?? [], 'Bot turn should include at least one shot.');

        $botShots = Move::where('game_id', $game->id)
            ->where('player_id', $bot->id)
            ->count();

        $this->assertGreaterThan(0, $botShots, 'Bot should create move records after responding.');
    }

    public function test_bot_does_not_respond_when_game_not_in_progress(): void
    {
        $user = User::factory()->create();

        $botMatches = app(BotMatchService::class);
        $placement = app(PlacementService::class);
        $battle = app(BattleService::class);

        $match = $botMatches->createFor($user);
        $player = $match['player'];
        $bot = $match['bot'];
        $game = $match['game'];

        $playerFleet = $placement->randomFleet();
        $player->update([
            'board' => $playerFleet['board'],
            'ships' => $playerFleet['ships'],
            'is_ready' => true,
        ]);

        // Game status remains NOT in progress

        $player->refresh();
        $bot->refresh();

        $result = $botMatches->syncBotTurn($player);

        $this->assertNull($result, 'Bot should not respond when game is not in progress.');

        $botShots = Move::where('game_id', $game->id)
            ->where('player_id', $bot->id)
            ->count();

        $this->assertEquals(0, $botShots, 'Bot should not create move records when game is not started.');
    }

    /**
     * @param array<int, array<int, int>> $board
     *
     * @return array{int, int}
     */
    private function findMissCoordinate(array $board): array
    {
        foreach ($board as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell === 0) {
                    return [$x, $y];
                }
            }
        }

        return [0, 0];
    }
}
