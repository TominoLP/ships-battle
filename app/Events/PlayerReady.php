<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class PlayerReady implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Player $player) {}

    public function broadcastOn(): Channel
    {
        // Broadcast to the game channel of this player
        return new Channel("game.{$this->player->game_id}");
    }

    public function broadcastAs(): string
    {
        return 'player_ready';
    }

    public function broadcastWith(): array
    {
        return [
            'player' => [
                'id' => $this->player->id,
                'name' => $this->player->name,
            ],
        ];
    }
}
