<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class PublicGameAvailable implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Game $game) {}

    public function broadcastOn(): Channel
    {
        return new Channel('game_public');
    }

    public function broadcastAs(): string
    {
        return 'public_game_available';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->game->id,
            'code' => $this->game->code,
            'enemy_name' => $this->game->players()->first()?->name ?? 'Waiting for player',
        ];
    }
}
