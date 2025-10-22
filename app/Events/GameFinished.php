<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class GameFinished implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Game $game, public Player $winner)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("game.{$this->game->id}");
    }

    public function broadcastAs(): string
    {
        return 'game_finished';
    }

    public function broadcastWith(): array
    {
        return [
            'game' => [
                'id' => $this->game->id,
                'code' => $this->game->code,
                'status' => $this->game->status,
            ],
            'winner' => [
                'id' => $this->winner->id,
                'name' => $this->winner->name,
            ],
        ];
    }
}
