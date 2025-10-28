<?php

namespace App\Http\Controllers;

use App\Models\PlayerGameHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function leaderboard(Request $request): JsonResponse
    {
        $metric = match ($request->query('metric')) {
            'wins' => 'wins',
            'winrate' => 'winrate',
            default => 'ships',
        };

        $baseQuery = PlayerGameHistory::query()
            ->selectRaw('user_id,
                SUM(ships_destroyed) as ships_destroyed,
                SUM(ships_lost) as ships_lost,
                SUM(shots_fired) as shots_fired,
                SUM(hits) as hits,
                SUM(abilities_used) as abilities_used,
                SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) as wins,
                COUNT(*) as games')
            ->with('user:id,name')
            ->groupBy('user_id');

        if ($metric === 'wins') {
            $baseQuery->orderByDesc('wins')->orderByDesc('ships_destroyed');
        } elseif ($metric === 'winrate') {
            $baseQuery
                ->orderByDesc(DB::raw('wins / NULLIF(games, 0)'))
                ->orderByDesc('wins');
        } else {
            $baseQuery->orderByDesc('ships_destroyed')->orderByDesc('wins');
        }

        $entries = $baseQuery->limit(10)->get()->map(function ($row) {
            $games = (int) $row->games;
            $wins = (int) $row->wins;

            return [
                'user_id' => $row->user_id,
                'name' => $row->user?->name ?? 'Unbekannt',
                'ships_destroyed' => (int) $row->ships_destroyed,
                'ships_lost' => (int) $row->ships_lost,
                'wins' => $wins,
                'games' => $games,
                'win_rate' => $games > 0 ? round(($wins / $games) * 100, 1) : 0.0,
                'shots_fired' => (int) $row->shots_fired,
                'hits' => (int) $row->hits,
                'abilities_used' => (int) $row->abilities_used,
            ];
        });

        return response()->json([
            'metric' => $metric,
            'players' => $entries,
        ]);
    }
}
