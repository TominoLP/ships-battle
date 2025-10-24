<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class RematchReady implements ShouldBroadcastNow
{
    use SerializesModels;

    /**
     * @param array<int, array{old_player_id:int,new_player_id:int,name:string,user_id:int|null,is_turn:bool}> $players
     */
    public function __construct(
        public Game $previousGame,
        public Game $nextGame,
        public array $players
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("game.{$this->previousGame->id}");
    }

    public function broadcastAs(): string
    {
        return 'rematch_ready';
    }

    public function broadcastWith(): array
    {
        return [
            'previous' => [
                'id' => $this->previousGame->id,
                'code' => $this->previousGame->code,
            ],
            'next' => [
                'id' => $this->nextGame->id,
                'code' => $this->nextGame->code,
            ],
            'players' => $this->players,
        ];
    }
}

