<?php

namespace App\Events;

use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ShipSunk implements ShouldBroadcastNow
{
    use SerializesModels;

    /**
     * @param Player $player The player who fired the sinking shot
     * @param int $size Length of the sunk ship
     * @param array $cells List of cells belonging to the ship (each: [x, y])
     */
    public function __construct(
        public Player $player,
        public int    $size,
        public array  $cells
    )
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("game.{$this->player->game_id}");
    }

    public function broadcastAs(): string
    {
        return 'ship_sunk';
    }

    public function broadcastWith(): array
    {
        return [
            'size' => $this->size,
            'cells' => array_map(fn($c) => ['x' => $c[0], 'y' => $c[1]], $this->cells),
            'player' => [
                'id' => $this->player->id,
                'name' => $this->player->name,
            ],
        ];
    }
}
