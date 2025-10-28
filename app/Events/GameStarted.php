<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class GameStarted implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Game $game, public Player $current) {}

    public function broadcastOn(): Channel
    {
        return new Channel("game.{$this->game->id}");
    }

    public function broadcastAs(): string
    {
        return 'game_started';
    }

    public function broadcastWith(): array
    {
        return [
            'game' => $this->game->only(['id', 'code', 'status', 'created_at', 'updated_at']),
            'current' => ['id' => $this->current->id, 'name' => $this->current->name],
            'players' => $this->game->players()->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->toArray(),
        ];
    }
}
