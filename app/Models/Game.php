<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * App\Models\Game
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $code
 * @property string $status
 * @property int|null $winner_player_id
 * @method static where(string $string, string $code)
 * @method static create()
 * @method static lockForUpdate()
 */
class Game extends Model
{
    const STATUS_WAITING = 'waiting';
    const STATUS_CREATING = 'creating';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    protected $fillable = [
        'code',
        'status',
        'winner_player_id',
    ];
    
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function ($game) {
            if (empty($game->status)) {
                $game->status = self::STATUS_WAITING;
                $game->code = self::generateUniqueCode();
            }
        });
    }
    
    private static function generateUniqueCode(): string
    {
        static $badWords = ['ass', 'sex', 'fuk', 'pis', 'cum', 'dck', 'cnt', 'tit', 'gay', 'wtf', 'fag',];
        
        do {
            $code = Str::lower(Str::random(6));
            $containsBadWord = collect($badWords)->contains(fn($word) =>
                str_contains(Str::lower($code), $word)
            );

        } while ($containsBadWord || self::where('code', $code)->exists());

        return $code;
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'game_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }


}
