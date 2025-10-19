<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PlayerJoined implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Game $game, public Player $player) {
        Log::info("PlayerJoined event created for player ID: {$this->player->id} in game ID: {$this->game->id}");
    }

    public function broadcastOn(): Channel
    {
        Log::info("Broadcasting PlayerJoined event to game channel: game.{$this->game->id}");
        return new Channel("game.{$this->game->id}");
    }

    public function broadcastAs(): string
    {
        return 'player_joined';
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
