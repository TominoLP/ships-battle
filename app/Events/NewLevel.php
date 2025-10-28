<?php

namespace App\Events;

use App\Models\Level;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class NewLevel implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public User $user,
        public Level $level,
        public Level|null $previousLevel
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("user.{$this->user->id}");
    }

    public function broadcastAs(): string
    {
        return 'new_level';
    }

    public function broadcastWith(): array
    {
        return [
            'level' => [
                'id' => $this->level->id,
                'name' => $this->level->name,
                'min_points' => (int) $this->level->min_points,
            ],
            'previous' => $this->previousLevel ? [
                'id' => $this->previousLevel->id,
                'name' => $this->previousLevel->name,
                'min_points' => (int) $this->previousLevel->min_points,
            ] : null,
        ];
    }
}
