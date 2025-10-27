<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class GameAborted implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public int $gameId, public string $code, public string $reason = 'opponent_left')
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("game.{$this->gameId}");
    }

    public function broadcastAs(): string
    {
        return 'game_aborted';
    }

    public function broadcastWith(): array
    {
        return [
            'game' => [
                'id' => $this->gameId,
                'code' => $this->code,
            ],
            'reason' => $this->reason,
        ];
    }
}
