<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Level
 * @property int $id
 * @property int $min_points
 * @property string $name
 * @property int $sort_index
 *
 * @method static orderBy(string $string, string $string1)
 * @method static updateOrCreate(array $array, array $array1)
 * @method static where(string $string, string $string1, int $param)
 * @method static find(int|null $current_level_id)
 * @method static firstOrCreate(int[] $array, string[] $array1)
 */
class Level extends Model
{
    use HasFactory;
    protected $fillable = [
        'min_points',
        'name',
        'sort_index'
    ];
}