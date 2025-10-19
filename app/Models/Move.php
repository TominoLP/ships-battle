<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Move
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $game_id
 * @property int $player_id
 * @property int $x
 * @property int $y
 * @property string $result
 * @method static where(string $string, string $code)
 * @method static create(array $array)
 */
class Move extends Model
{
    protected $fillable = [
        'game_id',
        'player_id',
        'x',
        'y',
        'result',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
