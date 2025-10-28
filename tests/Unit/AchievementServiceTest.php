<?php

use App\Events\AchievementUnlocked;
use App\Events\NewLevel;
use App\Models\Achievement;
use App\Models\Level;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Support\Facades\Event;

function makeLevel(string $name, int $min): Level
{
    return Level::firstOrCreate(
        ['min_points' => $min],
        ['name' => $name]
    );
}

function makeTestAchievement(string $key, array $steps = [], int $eventPoints = 0): Achievement
{
    $a = Achievement::create([
        'key' => $key,
        'name' => $key,
        'description' => null,
        'category' => 'test',
        'progress_type' => empty($steps) ? 'event' : 'counter',
        'is_tiered' => ! empty($steps),
        'event_points' => $eventPoints,
    ]);

    foreach ($steps as $k => $v) {
        if (is_array($v) && array_key_exists('threshold', $v)) {
            $a->steps()->create([
                'threshold' => (int) $v['threshold'],
                'points' => (int) $v['points'],
                'label' => null,
                'sort_index' => $k,
            ]);
        } else {
            $a->steps()->create([
                'threshold' => (int) $k,
                'points' => (int) $v,
                'label' => null,
                'sort_index' => $k,
            ]);
        }
    }

    return $a;
}

it('increments progress, unlocks steps, awards points, and levels up', function () {
    Event::fake([AchievementUnlocked::class, NewLevel::class]);

    $bronze = makeLevel('Bronze', 0);
    $silver = makeLevel('Silver', 10);
    $gold = makeLevel('Gold', 25);

    $user = User::factory()->create([
        'total_achievement_points' => 0,
        'current_level_id' => $bronze->id,
    ]);

    makeTestAchievement('test_ships_destroyed', [
        ['threshold' => 1, 'points' => 5],
        ['threshold' => 3, 'points' => 10],
        ['threshold' => 5, 'points' => 20],
    ]);

    $svc = app(AchievementService::class);

    $ua = $svc->increment($user, 'test_ships_destroyed', 1);
    $ua->refresh();
    $user->refresh();

    expect($ua->progress)->toBe(1)
        ->and($ua->highest_step_unlocked)->toBe(1)
        ->and((int) $user->total_achievement_points)->toBe(5)
        ->and($user->current_level_id)->toBe($bronze->id);

    Event::assertDispatched(AchievementUnlocked::class, 1);
    Event::assertNotDispatched(NewLevel::class);

    $svc->increment($user, 'test_ships_destroyed', 2);
    $ua->refresh();
    $user->refresh();

    expect($ua->progress)->toBe(3)
        ->and($ua->highest_step_unlocked)->toBe(3)
        ->and((int) $user->total_achievement_points)->toBe(15)
        ->and($user->current_level_id)->toBe($silver->id);

    Event::assertDispatched(AchievementUnlocked::class, 2);
    Event::assertDispatched(NewLevel::class, fn (NewLevel $e) => $e->level->id === $silver->id);

    $svc->increment($user, 'test_ships_destroyed', 2);
    $ua->refresh();
    $user->refresh();

    expect($ua->progress)->toBe(5)
        ->and($ua->highest_step_unlocked)->toBe(5)
        ->and((int) $user->total_achievement_points)->toBe(35)
        ->and($user->current_level_id)->toBe($gold->id);

    Event::assertDispatched(AchievementUnlocked::class, 3);
    Event::assertDispatched(NewLevel::class, fn (NewLevel $e) => $e->level->id === $gold->id);
});

it('does not fire unlock event when no new step threshold is reached', function () {
    Event::fake([AchievementUnlocked::class, NewLevel::class]);

    makeLevel('Bronze', 0);

    $user = User::factory()->create([
        'total_achievement_points' => 0,
        'current_level_id' => null,
    ]);

    makeTestAchievement('test_games_played', [
        10 => 10,
        20 => 20,
    ]);

    $svc = app(AchievementService::class);

    $ua = $svc->increment($user, 'test_games_played', 3);
    $ua->refresh();
    $user->refresh();

    expect($ua->progress)->toBe(3)
        ->and($ua->highest_step_unlocked)->toBeNull()
        ->and((int) $user->total_achievement_points)->toBe(0);

    Event::assertNotDispatched(AchievementUnlocked::class);
    Event::assertNotDispatched(NewLevel::class);
});

it('unlocks one-time event achievements and adds event points exactly once', function () {
    Event::fake([AchievementUnlocked::class, NewLevel::class]);

    makeLevel('Bronze', 0);
    $user = User::factory()->create(['total_achievement_points' => 0]);

    makeTestAchievement('test_got_bomb', [], 7);

    $svc = app(AchievementService::class);

    $ua = $svc->unlockEvent($user, 'test_got_bomb');
    $ua->refresh();
    $user->refresh();

    expect($ua->progress)->toBeGreaterThanOrEqual(1)
        ->and($ua->highest_step_unlocked)->toBe(1)
        ->and((int) $user->total_achievement_points)->toBe(7);

    Event::assertDispatched(AchievementUnlocked::class);

    $svc->unlockEvent($user, 'test_got_bomb');
    $ua->refresh();
    $user->refresh();

    expect((int) $user->total_achievement_points)->toBe(7)
        ->and($ua->highest_step_unlocked)->toBe(1);
});

it('grants multiple step points when skipping past several thresholds at once', function () {
    Event::fake([AchievementUnlocked::class, NewLevel::class]);

    makeLevel('Bronze', 0);
    $user = User::factory()->create(['total_achievement_points' => 0]);

    makeTestAchievement('test_abilities_used', [
        ['threshold' => 2, 'points' => 3],
        ['threshold' => 4, 'points' => 6],
        ['threshold' => 6, 'points' => 10],
    ]);

    $svc = app(AchievementService::class);

    $ua = $svc->increment($user, 'test_abilities_used', 6);
    $ua->refresh();
    $user->refresh();

    expect($ua->progress)->toBe(6)
        ->and($ua->highest_step_unlocked)->toBe(6)
        ->and((int) $user->total_achievement_points)->toBe(19);

    Event::assertDispatched(AchievementUnlocked::class, 1);
});
