<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * App\Models\UserAchievement
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $user_id
 * @property int $achievement_id
 * @property int $progress
 * @property int|null $highest_step_unlocked
 * @property Carbon|null $first_unlocked_at
 * @method static where(string $string, int $user_id)
 * @method static create(array $array)
 * @method static firstOrCreate(array $array)
 **/
class UserAchievement extends Model
{
    use HasFactory;
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'first_unlocked_at' => 'datetime',
    ];
    
    protected $fillable = [
        'user_id',
        'achievement_id',
        'progress',
        'highest_step_unlocked',
        'first_unlocked_at',
    ];


    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}