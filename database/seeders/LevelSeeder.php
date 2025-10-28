<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            0 => 'Level 1',
            100 => 'Level 2',
            250 => 'Level 3',
            500 => 'Level 4',
            900 => 'Level 5',
            1400 => 'Level 6',
            2000 => 'Level 7',
            2800 => 'Level 8',
            3800 => 'Level 9',
            5000 => 'Level 10',
            6500 => 'Level 11',
            8200 => 'Level 12',
            10000 => 'Level 13',
        ];

        $i = 0;
        foreach ($levels as $min => $name) {
            Level::updateOrCreate(
                ['min_points' => $min],
                ['name' => $name, 'sort_index' => $i++]
            );
        }
    }
}
