<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\PlayerGameHistory
 * 
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 * @property string $result
 * @property int $ships_destroyed
 * @property int $ships_lost
 * @property int $shots_fired
 * @property int $hits
 * @property int $abilities_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Game $game
 *
 * @method static updateOrCreate(array $array, array $array1)
 */
class PlayerGameHistory extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'result',
        'ships_destroyed',
        'ships_lost',
        'shots_fired',
        'hits',
        'abilities_used',
    ];

    protected $casts = [
        'ships_destroyed' => 'integer',
        'ships_lost' => 'integer',
        'shots_fired' => 'integer',
        'hits' => 'integer',
        'abilities_used' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
