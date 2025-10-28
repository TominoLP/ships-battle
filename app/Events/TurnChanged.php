<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class TurnChanged implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Game $game, public Player $player) {}

    public function broadcastOn(): Channel
    {
        return new Channel("game.{$this->game->id}");
    }

    public function broadcastAs(): string
    {
        return 'turn_changed';
    }

    public function broadcastWith(): array
    {
        return ['player' => ['id' => $this->player->id, 'name' => $this->player->name]];
    }
}
