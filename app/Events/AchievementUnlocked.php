<?php


namespace App\Events;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class AchievementUnlocked implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public User $user,
        public Achievement $achievement,
        public ?int $stepThreshold,
        public int $pointsAwarded,
        public int $totalPointsAfter
    )
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("user.{$this->user->id}");
    }

    public function broadcastAs(): string
    {
        return 'achievement_unlocked';
    }

    public function broadcastWith(): array
    {
        return [
            'achievement' => [
                'key' => $this->achievement->key,
                'name' => $this->achievement->name,
                'type' => $this->achievement->progress_type,
                'step' => $this->stepThreshold,
            ],
            'points_awarded' => $this->pointsAwarded,
            'total_points_after' => $this->totalPointsAfter,
        ];
    }
}
