<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;#
use Carbon\Carbon;

/**
 * App\Models\AchievementStep
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $achievement_id
 * @property int $threshold
 * @property string $label
 * @property int $sort_index
 * @property int $points
 *
 * @method static updateOrCreate(array $array, array $array1)
 */
class AchievementStep extends Model
{
    use HasFactory;
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'achievement_id',
        'threshold',
        'label',
        'sort_index',
        'points'
    ];


    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}