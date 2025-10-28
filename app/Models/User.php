<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $total_achievement_points
 * @property int|null $current_level_id
 * @property-read array $achievements
 * @property-read array $level
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'total_achievement_points',
        'current_level_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'level',
        'achievements',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function achievementsRelation(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function getLevelAttribute(): array
    {
        $points = (int) ($this->total_achievement_points ?? 0);

        $current = Level::orderBy('min_points', 'desc')
            ->where('min_points', '<=', $points)
            ->first();

        $next = Level::where('min_points', '>', $current?->min_points ?? -1)
            ->orderBy('min_points')
            ->first();

        return [
            'points' => $points,
            'current' => $current ? [
                'id' => $current->id,
                'name' => $current->name,
                'min_points' => (int) $current->min_points,
            ] : null,
            'next' => $next ? [
                'id' => $next->id,
                'name' => $next->name,
                'min_points' => (int) $next->min_points,
                'points_to_go' => max(0, (int) $next->min_points - $points),
            ] : null,
        ];
    }

    // NEW: achievements accessor on User (all achievements + this user's progress)
    public function getAchievementsAttribute(): array
    {
        $all = Achievement::with(['steps' => function ($q) {
            $q->orderBy('threshold')->select('id', 'achievement_id', 'threshold');
        }])->orderBy('id')->get();

        $progress = UserAchievement::where('user_id', $this->id)
            ->get()->keyBy('achievement_id');

        return $all->map(function (Achievement $a) use ($progress) {
            /** @var UserAchievement|null $ua */
            $ua = $progress->get($a->id);

            $steps = $a->steps->pluck('threshold')->all();
            $step_points = $a->steps()->pluck('points', 'threshold')->all();
            $maxStep = empty($steps) ? null : max($steps);

            $curr = (int) ($ua?->progress ?? 0);
            $highest = $ua?->highest_step_unlocked;
            $unlockedAt = $ua?->first_unlocked_at?->toIso8601String();

            $nextStep = null;
            $remaining = null;
            if ($a->progress_type === 'counter' && ! empty($steps)) {
                foreach ($steps as $t) {
                    if ($curr < $t) {
                        $nextStep = $t;
                        break;
                    }
                }
                if ($nextStep !== null) {
                    $remaining = max(0, $nextStep - $curr);
                }
            }

            $completed = false;
            if ($a->progress_type === 'event') {
                $completed = $ua !== null;
            } else {
                $completed = $maxStep !== null && $highest !== null && $highest >= $maxStep;
            }

            return [
                'key' => $a->key,
                'name' => $a->name,
                'description' => $a->description,
                'type' => $a->progress_type,
                'event_points' => (int) $a->event_points,
                'steps' => $step_points,
                'progress' => [
                    'value' => $curr,
                    'highest_step' => $highest,
                    'next_step' => $nextStep,
                    'remaining' => $remaining,
                    'completed' => (bool) $completed,
                    'unlocked_at' => $unlockedAt,
                ],
            ];
        })->values()->all();
    }
}
