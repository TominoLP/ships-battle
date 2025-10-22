<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Player
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $game_id
 * @property string $name
 * @property array $board
 * @property boolean $is_turn
 *
 * @method static where(string $string, int $game_id)
 * @method static findOrFail(mixed $player_id)
 * @method static create(array $array)
 * @method static lockForUpdate()
 */
class Player extends Model
{
    protected $fillable = [
        'game_id',
        'name',
        'board',
        'is_turn',
        'is_ready',
        'ships',
    ];

    protected $casts = [
        'is_ready' => 'boolean',
        'ships' => 'array',
        'board' => 'array',
        'is_turn' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'hits',
        'sunk_ships',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $player) {
            if (empty($player->board)) {
                $player->board = self::generateEmptyBoard();
            }
        });
    }

    private static function generateEmptyBoard(): array
    {
        return array_fill(0, 12, array_fill(0, 12, 0));
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function getHitsAttribute(): int
    {
        $enemy = $this->enemy();
        if (!$enemy) return 0;

        return collect($enemy->board)
            ->flatten()
            ->filter(fn($cell) => $cell === 2 || $cell === 3)
            ->count();
    }

    public function enemy(): self|null
    {
        return self::where('game_id', $this->game_id)
            ->where('id', '!=', $this->id)
            ->first();
    }

    public function getSunkShipsAttribute(): int
    {
        $enemy = $this->enemy();
        if (!$enemy) return 0;

        return collect($enemy->board)
            ->flatten()
            ->filter(fn($cell) => $cell === 4)
            ->count();
    }
}
