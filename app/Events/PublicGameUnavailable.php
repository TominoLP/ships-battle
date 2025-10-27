<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class PublicGameUnavailable implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public int $gameId)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game_public');
    }

    public function broadcastAs(): string
    {
        return 'public_game_unavailable';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->gameId,
        ];
    }
}
