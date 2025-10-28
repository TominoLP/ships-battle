<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\AchievementStep;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $make = function (array $data, array $steps = []) {
                /** @var Achievement $a */
                $a = Achievement::updateOrCreate(
                    ['key' => $data['key']],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'category' => $data['category'] ?? null,
                        'progress_type' => $data['progress_type'] ?? 'counter',
                        'is_tiered' => !empty($steps),
                        'event_points' => (int)($data['event_points'] ?? 0),
                    ]
                );


                if (!empty($steps)) {
                    $idx = 0;
                    foreach ($steps as $threshold => $points) {
                        AchievementStep::updateOrCreate(
                            ['achievement_id' => $a->id, 'threshold' => $threshold],
                            ['points' => $points, 'label' => null, 'sort_index' => $idx++]
                        );
                    }
                }


                return $a;
            };
            
            // Spiele ein Spiel (1, 10, 100, 250, 500)
            $make(['key'=>'games_played','name'=>'Spiele Spiele','description'=>'Spiele Partien.','category'=>'progress'], [
                1=>5, 10=>20, 100=>120, 250=>300, 500=>700,
            ]);
            
            // Zerstöre Schiffe (25, 100, 500, 1000)
            $make(['key'=>'ships_destroyed','name'=>'Zerstörer','description'=>'Zerstöre gegnerische Schiffe.','category'=>'combat'], [
                25=>25, 100=>100, 500=>600, 1000=>1500,
            ]);
            
            // Gewinne Spiele (5, 25, 75, 150)
            $make(['key'=>'games_won','name'=>'Sieger','description'=>'Gewinne Partien.','category'=>'progress'], [
                5=>50, 25=>250, 75=>900, 150=>2000,
            ]);
            
            // Nutze Fähigkeiten (20, 50, 100, 250)
            $make(['key'=>'abilities_used','name'=>'Taktiker','description'=>'Nutze Fähigkeiten.','category'=>'progress'], [
                20=>20, 50=>60, 100=>160, 250=>500,
            ]);
            
            // Spezielle Erfolgen
            $make(['key'=>'win_streak_5','name'=>'Heißgelaufen','description'=>'Gewinne 5 Spiele in Folge.','category'=>'streak','progress_type'=>'event','event_points'=>400]);
            $make(['key'=>'got_bomb','name'=>'Bombenträger','description'=>'Erhalte eine Bombe.','category'=>'special','progress_type'=>'event','event_points'=>50]);
            $make(['key'=>'multi_kill_3_in_turn','name'=>'Dreifachschlag','description'=>'Zerstöre 3 Schiffe in einem Zug.','category'=>'combat','progress_type'=>'event','event_points'=>150]);
            $make(['key'=>'multi_kill_5_in_turn','name'=>'Fünffachschlag','description'=>'Zerstöre 5 Schiffe in einem Zug.','category'=>'combat','progress_type'=>'event','event_points'=>400]);
            $make(['key'=>'first_turn_scout_6','name'=>'Früher Späher I','description'=>'Kläre im ersten Zug 6 Schiffe auf.','category'=>'special','progress_type'=>'event','event_points'=>120]);
            $make(['key'=>'first_turn_scout_8','name'=>'Früher Späher II','description'=>'Kläre im ersten Zug 8 Schiffe auf.','category'=>'special','progress_type'=>'event','event_points'=>300]);
        });
    }
}
