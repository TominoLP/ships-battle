<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Achievement
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $key
 * @property string $name
 * @property string $description
 * @property string $category
 * @property string $progress_type
 * @property bool $is_tiered
 * @property int $event_points
 *
 * @method static where(string $string, string $key)
 * @method static create(array $array)
 * @method static updateOrCreate(array $array, array $array1)
 **/
class Achievement extends Model
{
    use HasFactory;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_tiered' => 'boolean',
    ];

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'progress_type',
        'is_tiered',
        'event_points',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(AchievementStep::class)->orderBy('sort_index');
    }
}
