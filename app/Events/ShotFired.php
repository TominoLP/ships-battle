<?php

namespace App\Events;

use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ShotFired implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public Player $player,
        public int $x,
        public int $y,
        public string $result
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("game.{$this->player->game_id}");
    }

    public function broadcastAs(): string
    {
        return 'shot_fired';
    }

    public function broadcastWith(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'result' => $this->result,
            'player' => [
                'id' => $this->player->id,
                'name' => $this->player->name,
            ],
        ];
    }
}
