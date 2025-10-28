<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'name' => $this->faker->name(),
            'is_turn' => false,
            'is_ready' => false,
            'wants_rematch' => false,
            'board' => null,
            'ships' => null,
            'ability_usage' => [
                'plane' => 1,
                'comb' => 1,
                'splatter' => 2,
            ],
            'turn_kills' => 0,
        ];
    }
}
