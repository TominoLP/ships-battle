<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
