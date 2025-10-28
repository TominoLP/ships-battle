<?php

namespace App\Services;

use App\Events\AchievementUnlocked;
use App\Events\NewLevel;
use App\Models\Achievement;
use App\Models\Level;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    public function increment(User $user, string $achievementKey, int $by = 1): UserAchievement
    {
        return DB::transaction(function () use ($user, $achievementKey, $by) {
            $achievement = Achievement::where('key', $achievementKey)->firstOrFail();

            $userAchievement = UserAchievement::firstOrCreate([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
            ]);

            $userAchievement->progress += $by;

            $steps = $achievement->steps()->orderBy('threshold')->get();
            $newHighest = $steps->where('threshold', '<=', $userAchievement->progress)->max('threshold');

            if ($newHighest && ($userAchievement->highest_step_unlocked === null || $newHighest > $userAchievement->highest_step_unlocked)) {
                $gained = $steps
                    ->where('threshold', '>', (int) ($userAchievement->highest_step_unlocked ?? 0))
                    ->where('threshold', '<=', $newHighest)
                    ->sum('points');

                $userAchievement->highest_step_unlocked = $newHighest;
                $userAchievement->first_unlocked_at = $userAchievement->first_unlocked_at ?? now();
                $userAchievement->save();

                if ($gained > 0) {
                    $this->addPointsAndUpdateLevel($user, $gained, $achievement, $newHighest);
                } else {
                    $this->emitProgressEvent($user, $achievement, $newHighest, 0);
                }
            } else {
                $userAchievement->save();
            }

            return $userAchievement;
        });
    }

    public function unlockEvent(User $user, string $achievementKey): UserAchievement
    {
        return DB::transaction(function () use ($user, $achievementKey) {
            $achievement = Achievement::where('key', $achievementKey)->firstOrFail();
            $userAchievement = UserAchievement::firstOrCreate([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
            ]);

            if ($userAchievement->highest_step_unlocked === null) {
                $userAchievement->progress = max(1, $userAchievement->progress);
                $userAchievement->highest_step_unlocked = 1; // sentinel for event achievements
                $userAchievement->first_unlocked_at = $userAchievement->first_unlocked_at ?? now();
                $userAchievement->save();

                if ($achievement->event_points > 0) {
                    $this->addPointsAndUpdateLevel($user, $achievement->event_points, $achievement, null);
                } else {
                    $this->emitProgressEvent($user, $achievement, null, 0);
                }
            }

            return $userAchievement;
        });
    }

    protected function addPointsAndUpdateLevel(User $user, int $points, Achievement $achievement, ?int $stepThreshold = null): void
    {
        $prevTotal = (int) $user->total_achievement_points;
        $prevLevel = $user->current_level_id ? Level::find($user->current_level_id) : null;

        $user->total_achievement_points = $prevTotal + $points;

        $newLevel = Level::orderBy('min_points', 'desc')
            ->where('min_points', '<=', $user->total_achievement_points)
            ->first();

        $user->current_level_id = $newLevel?->id;
        $user->save();

        event(new AchievementUnlocked($user, $achievement, $stepThreshold, $points, (int) $user->total_achievement_points));

        if (($newLevel?->id ?? null) !== ($prevLevel?->id ?? null) && $newLevel) {
            event(new NewLevel($user, $newLevel, $prevLevel));
        }
    }

    protected function emitProgressEvent(User $user, Achievement $achievement, ?int $stepThreshold, int $pointsAwarded): void
    {
        event(new AchievementUnlocked(
            $user,
            $achievement,
            $stepThreshold,
            $pointsAwarded,
            (int) $user->total_achievement_points
        ));
    }
}
